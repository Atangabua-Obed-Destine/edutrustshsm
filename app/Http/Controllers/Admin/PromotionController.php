<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\AcademicSession;
use App\Models\ClassSection;
use App\Models\Form;
use App\Models\SchoolSetting;
use App\Models\StudentEnrollment;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class PromotionController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'promotion';

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
        $settings = SchoolSetting::current();
        $threshold = $settings?->promotion_threshold ?? 10.0;

        if ($classSectionId) {
            $selectedClass = ClassSection::with('form')->find($classSectionId);
            $students = StudentEnrollment::with(['student', 'classSection.form', 'termResults'])
                ->where('class_section_id', $classSectionId)
                ->when($fromSessionId, fn ($q) => $q->where('academic_session_id', $fromSessionId))
                ->where('status', 'active')
                ->get()
                ->map(function ($enrollment) use ($threshold) {
                    // term_results.term_average — there is no `overall_average` column.
                    $termAvg = $enrollment->termResults->avg('term_average');
                    $enrollment->computed_average = $termAvg ? round($termAvg, 2) : null;
                    $enrollment->recommended_decision = null;

                    if ($termAvg !== null) {
                        $enrollment->recommended_decision = $termAvg >= $threshold ? 'promote' : 'repeat';
                    }

                    return $enrollment;
                })
                ->sortByDesc('computed_average');
        }

        // Next session classes for promotion target
        $nextSession = AcademicSession::where('start_date', '>', $currentSession?->end_date ?? now())
            ->orderBy('start_date')
            ->first();

        $targetClasses = collect();
        if ($nextSession) {
            // Sections are session-agnostic, so every active section is a valid
            // promotion target for the next session.
            $targetClasses = ClassSection::with('form')
                ->where('is_active', true)
                ->orderBy('name')
                ->get();
        }

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

        $nextSession = AcademicSession::where('start_date', '>', AcademicSession::current()?->end_date ?? now())
            ->orderBy('start_date')
            ->first();

        if (!$nextSession) {
            return back()->with('error', 'No next academic session found. Please create one first.');
        }

        $promoted = 0;
        $repeated = 0;
        $graduated = 0;
        $skipped = 0;

        DB::transaction(function () use ($validated, $nextSession, &$promoted, &$repeated, &$graduated, &$skipped) {
            foreach ($validated['decisions'] as $decision) {
                $enrollment = StudentEnrollment::with(['student', 'classSection.form'])->find($decision['enrollment_id']);
                if (!$enrollment) continue;

                switch ($decision['action']) {
                    case 'promote':
                        if (empty($decision['target_class_id'])) continue 2;

                        $enrollment->update(['status' => 'promoted']);

                        // Create new enrollment in next session
                        StudentEnrollment::create([
                            'student_id' => $enrollment->student_id,
                            'academic_session_id' => $nextSession->id,
                            'class_section_id' => $decision['target_class_id'],
                            'stream_id' => $enrollment->stream_id,
                            'residence_type' => $enrollment->residence_type,
                            'enrollment_date' => $nextSession->start_date,
                            'status' => 'active',
                        ]);
                        $promoted++;
                        break;

                    case 'repeat':
                        $enrollment->update(['status' => 'repeated']);

                        // Find equivalent class in next session (same form)
                        $targetClass = $decision['target_class_id']
                            ? ClassSection::find($decision['target_class_id'])
                            : ClassSection::where('form_id', $enrollment->classSection->form_id)
                                ->where('is_active', true)
                                ->first();

                        if ($targetClass) {
                            StudentEnrollment::create([
                                'student_id' => $enrollment->student_id,
                                'academic_session_id' => $nextSession->id,
                                'class_section_id' => $targetClass->id,
                                'stream_id' => $enrollment->stream_id,
                                'residence_type' => $enrollment->residence_type,
                                'enrollment_date' => $nextSession->start_date,
                                'status' => 'active',
                            ]);
                        }
                        $repeated++;
                        break;

                    case 'graduate':
                        $enrollment->update(['status' => 'completed']);
                        $enrollment->student->update(['status' => 'graduated']);
                        $graduated++;
                        break;

                    case 'skip':
                        $skipped++;
                        break;
                }
            }
        });

        $message = "Promotion complete: {$promoted} promoted, {$repeated} repeated, {$graduated} graduated, {$skipped} skipped.";
        return redirect()->route('admin.promotion.index')
            ->with('success', $message);
    }
}
