<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\MarksSubmission;
use App\Models\NurseryAssessment;
use App\Models\Sequence;
use App\Models\StudentEnrollment;
use App\Models\Term;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A teacher's subjects as cards: one per subject per class, each carrying
 * the term's sequences with how far the marks have got, so a tap opens the
 * right marks grid. Shared by the teacher's dashboard and the marks screen,
 * as in the reference system's "My Assigned Courses".
 *
 * Unlike the reference, a card offers only the sequences that class sits
 * this term, each with its status already shown: there is no exam-type list
 * to pick from and no status that appears only after a choice.
 */
class TeacherSubjects
{
    public function __construct(private TeachingScope $scope)
    {
    }

    /**
     * @return Collection<int, array{section: \App\Models\ClassSection, subject: ?\App\Models\Subject, nursery: bool, class_teacher: bool, pupils: int, sequences: list<array<string, mixed>>, done: bool, sheets_done: int}>
     */
    public function cards(User $user, ?AcademicSession $session, ?Term $term): Collection
    {
        $cards = collect();

        foreach ($this->scope->myClasses($user) as $class) {
            $section = $class['section'];
            $pupils = StudentEnrollment::where('class_section_id', $section->id)
                ->when($session, fn ($q) => $q->where('academic_session_id', $session->id))
                ->where('status', 'active')->distinct()->count('student_id');

            // A nursery class is assessed by competence, by its class teacher:
            // one card for the class rather than one per subject.
            if ($section->form->level === 'nursery') {
                if (! $class['class_teacher']) {
                    continue;
                }
                $done = 0;
                if ($term) {
                    $enrolments = StudentEnrollment::where('class_section_id', $section->id)
                        ->when($session, fn ($q) => $q->where('academic_session_id', $session->id))
                        ->where('status', 'active')->pluck('id');
                    $total = app(NurseryAssessmentService::class)->competencesFor($section->form)->sum(fn ($a) => $a->competences->count());
                    $done = $total > 0 ? NurseryAssessment::whereIn('student_enrollment_id', $enrolments)->where('term_id', $term->id)
                        ->selectRaw('student_enrollment_id, count(*) n')->groupBy('student_enrollment_id')->get()->where('n', '>=', $total)->count() : 0;
                }
                $cards->push([
                    'section' => $section, 'subject' => null, 'nursery' => true, 'class_teacher' => true,
                    'pupils' => $pupils, 'sequences' => [], 'sheets_done' => $done,
                    'done' => $pupils > 0 && $done >= $pupils,
                ]);

                continue;
            }

            $sequences = $term ? Sequence::whereIn('id', DB::table('form_sequence')->where('form_id', $section->form_id)->where('term_id', $term->id)->pluck('sequence_id'))
                ->orderBy('sequence_number')->orderBy('id')->get() : collect();
            $submissions = MarksSubmission::forSession($session?->id)->where('class_section_id', $section->id)
                ->whereIn('sequence_id', $sequences->pluck('id'))->get()
                ->keyBy(fn ($s) => $s->subject_id.':'.$s->sequence_id);

            foreach ($class['subjects'] as $subject) {
                $cells = $sequences->map(function ($sequence) use ($submissions, $subject, $section, $term, $session) {
                    $submission = $submissions->get($subject->id.':'.$sequence->id);

                    return [
                        'sequence' => $sequence,
                        'status' => $submission?->status ?? 'none',
                        'entered' => (int) ($submission?->marks_entered ?? 0),
                        'url' => route('admin.marks.index', [
                            'academic_session_id' => $session?->id,
                            'education_system' => $section->form->education_system,
                            'form_id' => $section->form_id,
                            'class_section_id' => $section->id,
                            'subject_id' => $subject->id,
                            'term_id' => $term?->id,
                            'sequence_id' => $sequence->id,
                        ]),
                    ];
                })->all();

                $cards->push([
                    'section' => $section, 'subject' => $subject, 'nursery' => false, 'class_teacher' => $class['class_teacher'],
                    'pupils' => $pupils, 'sequences' => $cells, 'sheets_done' => 0,
                    'done' => $cells !== [] && collect($cells)->every(fn ($c) => in_array($c['status'], ['approved', 'published'], true)),
                ]);
            }
        }

        return $cards->values();
    }

    /** The first sequence still waiting on the teacher, for a card's main button. */
    public function nextUrl(array $card): ?string
    {
        $cells = collect($card['sequences']);

        return ($cells->first(fn ($c) => in_array($c['status'], ['returned'], true))
            ?? $cells->first(fn ($c) => in_array($c['status'], ['none', 'draft'], true))
            ?? $cells->first())['url'] ?? null;
    }

    /** How a sequence's marks stand, as a short label and a colour. */
    public static function badge(array $cell, int $pupils): array
    {
        return match ($cell['status']) {
            'approved' => [__('Approved'), 'bg-emerald-100 text-emerald-800'],
            'published' => [__('Published'), 'bg-emerald-100 text-emerald-800'],
            'submitted' => [__('Submitted'), 'bg-sky-100 text-sky-800'],
            'returned' => [__('Returned'), 'bg-red-100 text-red-800'],
            'draft' => [__(':n/:t entered', ['n' => $cell['entered'], 't' => $pupils]), 'bg-amber-100 text-amber-800'],
            default => [__('Not started'), 'bg-gray-100 text-gray-600'],
        };
    }
}
