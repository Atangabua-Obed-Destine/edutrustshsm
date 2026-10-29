<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\ClassSection;
use App\Models\Form;
use App\Models\NurseryAssessment;
use App\Models\StudentEnrollment;
use App\Models\Term;
use App\Services\NurseryAssessmentService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * The nursery teacher's sheet: a class, a term, a pupil, and for every
 * competence whether it is acquired, in progress or not yet.
 *
 * Gated like marks entry, because that is what it is for a nursery class.
 */
class NurseryAssessmentController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'marks-entry';

    public function __construct(private NurseryAssessmentService $nursery) {}

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('marks-entry.view', ['index', 'edit']),
            static::can('marks-entry.enter', ['update']),
        ];
    }

    public function index(Request $request)
    {
        $session = AcademicSession::current();
        $terms = Term::orderBy('term_number')->get();

        $sections = ClassSection::query()
            ->where('is_active', true)
            ->whereHas('form', fn ($q) => $q->forCurrentLevel()->where('level', 'nursery')->where('is_active', true))
            ->with('form')
            ->get()
            ->sortBy(fn ($s) => [$s->form->display_order, $s->name])
            ->values();

        $sectionId = $request->integer('class_section_id') ?: null;
        $termId = $request->integer('term_id') ?: (Term::current()?->id ?? $terms->first()?->id);

        $section = $sectionId ? $sections->firstWhere('id', $sectionId) : null;
        $term = $termId ? $terms->firstWhere('id', $termId) : null;

        $progress = ($section && $term && $session)
            ? $this->nursery->progress($section, $term, $session->id)
            : null;

        return view('admin.nursery.index', [
            'sections' => $sections,
            'terms' => $terms,
            'section' => $section,
            'term' => $term,
            'session' => $session,
            'progress' => $progress,
            'catalogueEmpty' => $this->nursery->catalogueIsEmpty(),
        ]);
    }

    public function edit(StudentEnrollment $enrollment, Term $term)
    {
        $enrollment->load('student', 'classSection.form', 'classSection.classTeacher');
        abort_unless(NurseryAssessmentService::isNursery($enrollment->classSection->form), 404);

        $sheet = $this->nursery->sheet($enrollment, $term);

        return view('admin.nursery.sheet', $sheet + [
            'enrollment' => $enrollment,
            'term' => $term,
            'next' => $this->nextPupil($enrollment),
            'labels' => NurseryAssessment::labels(),
        ]);
    }

    public function update(Request $request, StudentEnrollment $enrollment, Term $term)
    {
        $enrollment->load('classSection.form');
        abort_unless(NurseryAssessmentService::isNursery($enrollment->classSection->form), 404);

        $data = $request->validate([
            'ratings' => ['nullable', 'array'],
            'ratings.*' => ['nullable', 'in:'.implode(',', NurseryAssessment::RATINGS)],
            'class_teacher_remark' => ['nullable', 'string', 'max:1000'],
            'days_present' => ['nullable', 'integer', 'min:0', 'max:200'],
            'days_absent' => ['nullable', 'integer', 'min:0', 'max:200'],
            'then' => ['nullable', 'in:stay,next,list'],
        ]);

        $count = $this->nursery->save(
            $enrollment,
            $term,
            array_filter($data['ratings'] ?? []),
            [
                'class_teacher_remark' => $data['class_teacher_remark'] ?? null,
                'days_present' => $data['days_present'] ?? null,
                'days_absent' => $data['days_absent'] ?? null,
            ],
            $request->user()?->id,
        );

        $message = trans_choice('{0} Sheet saved.|{1} Sheet saved. :count competence rated.|[2,*] Sheet saved. :count competences rated.', $count, ['count' => $count]);
        $next = $this->nextPupil($enrollment);

        return match ($data['then'] ?? 'stay') {
            'next' => $next
                ? redirect()->route('admin.nursery.edit', [$next, $term])->with('success', $message)
                : redirect()->route('admin.nursery.index', ['class_section_id' => $enrollment->class_section_id, 'term_id' => $term->id])
                    ->with('success', $message.' '.__('That was the last pupil in the class.')),
            'list' => redirect()->route('admin.nursery.index', ['class_section_id' => $enrollment->class_section_id, 'term_id' => $term->id])->with('success', $message),
            default => back()->with('success', $message),
        };
    }

    /** The pupil after this one in the class, by surname, so a teacher can go down the register. */
    private function nextPupil(StudentEnrollment $enrollment): ?StudentEnrollment
    {
        $ordered = StudentEnrollment::query()
            ->where('class_section_id', $enrollment->class_section_id)
            ->where('academic_session_id', $enrollment->academic_session_id)
            ->where('status', 'active')
            ->with('student')
            ->get()
            ->sortBy(fn ($e) => $e->student->last_name.' '.$e->student->first_name)
            ->values();

        $index = $ordered->search(fn ($e) => $e->id === $enrollment->id);

        return $index === false ? null : $ordered->get($index + 1);
    }
}
