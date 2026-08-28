<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\AcademicSession;
use App\Models\ClassSection;
use App\Models\SchoolSetting;
use App\Models\StudentEnrollment;
use App\Models\Term;
use App\Services\PromotionEligibilityService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class PromotionController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'promotion';

    public function __construct(private PromotionEligibilityService $eligibility) {}

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('promotion.view', ['index']),
            static::can('promotion.process', ['process']),
        ];
    }

    public function index(Request $request)
    {
        $currentSession = AcademicSession::current();
        $sessions = AcademicSession::orderByDesc('start_date')->get();
        $fromSessionId = $request->input('from_session_id', $currentSession?->id);

        // Class sections are NOT session-scoped (they belong to a Form and are
        // reused every year). "Classes in session X" therefore means the sections
        // that actually have enrollments in X.
        $classSections = $fromSessionId
            ? ClassSection::with('form')
                ->where('is_active', true)
                ->whereHas('studentEnrollments', fn ($q) => $q->where('academic_session_id', $fromSessionId))
                ->orderBy('name')
                ->get()
            : collect();

        $classSectionId = $request->input('class_section_id');
        $students = collect();
        $selectedClass = null;
        $threshold = SchoolSetting::current()?->promotion_threshold ?? 10.0;

        if ($classSectionId && $fromSessionId) {
            $selectedClass = ClassSection::with('form')->find($classSectionId);

            // The eligibility service owns the arithmetic and the verdict. The
            // screen used to compute a recommendation off a column that does not
            // exist, so every recommendation came out null.
            $students = $this->eligibility->forClass((int) $classSectionId, (int) $fromSessionId)
                ->map(function ($assessment) {
                    $enrollment = $assessment->enrollment;
                    $enrollment->computed_average = $assessment->average;
                    $enrollment->recommended_decision = $assessment->recommendation;
                    $enrollment->is_eligible = $assessment->eligible;
                    $enrollment->ineligible_reason = $assessment->reason;
                    $enrollment->terms_completed = $assessment->terms_completed;
                    $enrollment->terms_expected = $assessment->terms_expected;

                    return $enrollment;
                })
                ->sortByDesc(fn ($e) => $e->computed_average ?? -1);
        }

        $nextSession = $currentSession ? $this->eligibility->nextSession($currentSession) : null;

        // Sections are session-agnostic, so every active section is a valid
        // promotion target for the next session.
        $targetClasses = $nextSession
            ? ClassSection::with('form')->where('is_active', true)->orderBy('name')->get()
            : collect();

        return view('admin.promotion.index', compact(
            'sessions', 'fromSessionId', 'classSections', 'classSectionId',
            'students', 'selectedClass', 'threshold', 'nextSession', 'targetClasses'
        ));
    }

    public function process(Request $request)
    {
        $validated = $request->validate([
            'decisions' => ['required', 'array', 'min:1'],
            'decisions.*.enrollment_id' => ['required', 'exists:student_enrollments,id'],
            'decisions.*.action' => ['required', 'in:promote,repeat,graduate,skip'],
            'decisions.*.target_class_id' => ['nullable', 'exists:class_sections,id'],
        ]);

        $currentSession = AcademicSession::current();
        $nextSession = $currentSession ? $this->eligibility->nextSession($currentSession) : null;

        if (! $nextSession) {
            return back()->with('error', __('No next academic session found. Please create one first.'));
        }

        // A promoted student starts the new year at its beginning. Without a
        // term_id the new enrollment is invisible to every marks and report-card
        // query, so this is a hard requirement, not a nicety.
        $landingTerm = $this->eligibility->landingTerm();

        if (! $landingTerm) {
            return back()->with('error', __('No terms are configured, so promoted students cannot be placed. Set up the academic terms first.'));
        }

        $counts = ['promoted' => 0, 'repeated' => 0, 'graduated' => 0, 'skipped' => 0];
        $refused = [];

        DB::transaction(function () use ($validated, $nextSession, $landingTerm, &$counts, &$refused) {
            $enrollments = StudentEnrollment::with(['student', 'classSection.form'])
                ->whereIn('id', array_column($validated['decisions'], 'enrollment_id'))
                ->get()
                ->keyBy('id');

            // Stamp the year's annual average and rank before closing it out.
            // final_average / final_rank have been displayed on the student page
            // since the beginning and written by nothing at all.
            $enrollments
                ->groupBy(fn ($e) => $e->class_section_id.':'.$e->academic_session_id)
                ->each(function ($rows) {
                    $first = $rows->first();
                    $this->eligibility->recordAnnualResults($first->class_section_id, $first->academic_session_id);
                });

            foreach ($validated['decisions'] as $decision) {
                $enrollment = $enrollments->get((int) $decision['enrollment_id']);

                if (! $enrollment) {
                    continue;
                }

                switch ($decision['action']) {
                    case 'promote':
                        if (empty($decision['target_class_id'])) {
                            $refused[] = $this->name($enrollment).' — '.__('no target class was chosen');
                            continue 2;
                        }

                        // The gate. Promotion previously moved students up with no
                        // marks at all, or with marks still sitting in draft.
                        $assessment = $this->eligibility->assess($enrollment, $enrollment->academic_session_id);

                        if (! $assessment->eligible) {
                            $refused[] = $this->name($enrollment).' — '.$assessment->reason;
                            continue 2;
                        }

                        $enrollment->update(['status' => 'promoted']);
                        $this->enroll($enrollment, (int) $decision['target_class_id'], $nextSession, $landingTerm);
                        $counts['promoted']++;
                        break;

                    case 'repeat':
                        $enrollment->update(['status' => 'repeated']);

                        // Repeating means the same year again: default to the class
                        // the student is already in, not whichever section of the
                        // form happens to sort first.
                        $targetId = $decision['target_class_id'] ?: $enrollment->class_section_id;

                        $this->enroll($enrollment, (int) $targetId, $nextSession, $landingTerm);
                        $counts['repeated']++;
                        break;

                    case 'graduate':
                        $enrollment->update(['status' => 'completed']);
                        $enrollment->student->update(['status' => 'graduated']);
                        $counts['graduated']++;
                        break;

                    case 'skip':
                        $counts['skipped']++;
                        break;
                }
            }
        });

        $message = __('Promotion complete: :promoted promoted, :repeated repeated, :graduated graduated, :skipped skipped.', [
            'promoted' => $counts['promoted'],
            'repeated' => $counts['repeated'],
            'graduated' => $counts['graduated'],
            'skipped' => $counts['skipped'],
        ]);

        $redirect = redirect()->route('admin.promotion.index')->with('success', $message);

        if ($refused !== []) {
            // Name who was refused and why. Silently dropping them would leave
            // students behind with nothing on screen to say so.
            $redirect->with('error', __('Not promoted (:count):', ['count' => count($refused)]).' '.implode('; ', $refused));
        }

        return $redirect;
    }

    /**
     * Place a student into the next session's first term.
     *
     * Idempotent: the unique key is (student, session, term), so re-running a
     * promotion returns the existing row rather than failing halfway through.
     */
    private function enroll(StudentEnrollment $from, int $classSectionId, AcademicSession $session, Term $term): void
    {
        $enrollment = StudentEnrollment::firstOrCreate(
            [
                'student_id' => $from->student_id,
                'academic_session_id' => $session->id,
                'term_id' => $term->id,
            ],
            [
                // Stamped explicitly: BelongsToBranch does not stamp branch_id
                // while an admin is in All-Branches mode, which would orphan the
                // row from every scoped query.
                'branch_id' => $from->branch_id,
                'class_section_id' => $classSectionId,
                'stream_id' => $from->stream_id,
                'residence_type' => $from->residence_type,
                'enrollment_date' => $session->start_date,
                'status' => 'active',
            ]
        );

        // Without this the student is enrolled but registered for no subjects, so
        // marks entry and report cards show them with an empty subject list.
        $enrollment->syncCoreSubjects();
    }

    private function name(StudentEnrollment $enrollment): string
    {
        return trim(($enrollment->student?->first_name ?? '').' '.($enrollment->student?->last_name ?? ''))
            ?: '#'.$enrollment->id;
    }
}
