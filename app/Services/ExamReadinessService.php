<?php

namespace App\Services;

use App\Models\ClassSection;
use App\Models\MarksSubmission;
use App\Models\Sequence;
use App\Models\Term;
use App\Models\TermResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Can this term's results be published yet?
 *
 * Nothing asked this before. Report cards could be generated from whatever
 * marks happened to exist and published in one click, so a term missing two
 * subjects produced averages that looked exactly as finished as a complete
 * one — and a pupil whose teacher had not submitted was ranked against
 * classmates on fewer subjects than they sat.
 *
 * The unit of work is a marks submission: one class, one subject, one
 * sequence. Expected submissions are derived from what pupils are actually
 * enrolled to study, so the check cannot be satisfied by a subject nobody
 * takes, and cannot be defeated by one nobody remembered to set up.
 *
 * Findings are split by consequence. A BLOCKER means publishing now would
 * put a wrong number on a report card. A WARNING means something is untidy
 * but the figures would still be right.
 */
class ExamReadinessService
{
    public const READY = 'ready';
    public const WARNING = 'warning';
    public const BLOCKER = 'blocker';

    /**
     * @return array<string, mixed>
     */
    public function report(int $termId, ?int $formId = null, ?int $classSectionId = null): array
    {
        $sequences = Sequence::where('term_id', $termId)->orderBy('sequence_number')->get();
        $sections = $this->sections($formId, $classSectionId);

        $expected = $this->expectedUnits($sections, $sequences);
        $submissions = $this->submissions($sections, $sequences);

        $pending = $this->pendingUnits($expected, $submissions);
        $withoutMarks = $this->studentsWithoutMarks($sections, $sequences);
        $results = $this->resultCounts($termId, $sections);

        $checks = $this->checks($sequences, $expected, $pending, $withoutMarks, $results);

        return [
            'term' => Term::find($termId),
            'sequences' => $sequences,
            'checks' => $checks,
            'verdict' => $this->verdict($checks),
            'pending' => $pending,
            'students_without_marks' => $withoutMarks,
            'classes' => $this->byClass($sections, $expected, $pending, $results),
            'totals' => [
                'expected' => $expected->count(),
                'pending' => $pending->count(),
                'classes' => $sections->count(),
            ],
        ];
    }

    /** @return Collection<int, ClassSection> */
    private function sections(?int $formId, ?int $classSectionId): Collection
    {
        return ClassSection::where('is_active', true)
            ->whereHas('form', fn ($q) => $q->forCurrentLevel())
            ->when($classSectionId, fn ($q) => $q->where('id', $classSectionId))
            ->when($formId && ! $classSectionId, fn ($q) => $q->where('form_id', $formId))
            ->with('form:id,name')
            ->orderBy('name')
            ->get();
    }

    /**
     * Every submission that ought to exist: each subject a class's pupils are
     * enrolled to study, once per sequence.
     *
     * Derived from enrolments rather than from the curriculum, because what
     * pupils are actually registered for is what their report cards will be
     * built from.
     *
     * @param  Collection<int, ClassSection>  $sections
     * @param  Collection<int, Sequence>  $sequences
     * @return Collection<int, array<string, mixed>> keyed "section:subject:sequence"
     */
    private function expectedUnits(Collection $sections, Collection $sequences): Collection
    {
        if ($sections->isEmpty() || $sequences->isEmpty()) {
            return collect();
        }

        $pairs = DB::table('student_subjects')
            ->join('student_enrollments', 'student_subjects.student_enrollment_id', '=', 'student_enrollments.id')
            ->join('subjects', 'student_subjects.subject_id', '=', 'subjects.id')
            ->whereIn('student_enrollments.class_section_id', $sections->pluck('id'))
            ->where('student_enrollments.status', 'active')
            ->select(
                'student_enrollments.class_section_id',
                'student_subjects.subject_id',
                'subjects.name as subject_name',
                DB::raw('COUNT(DISTINCT student_enrollments.id) as pupils')
            )
            ->groupBy('student_enrollments.class_section_id', 'student_subjects.subject_id', 'subjects.name')
            ->get();

        $units = collect();

        foreach ($pairs as $pair) {
            foreach ($sequences as $sequence) {
                $key = $pair->class_section_id.':'.$pair->subject_id.':'.$sequence->id;

                $units[$key] = [
                    'class_section_id' => $pair->class_section_id,
                    'subject_id' => $pair->subject_id,
                    'subject' => $pair->subject_name,
                    'sequence_id' => $sequence->id,
                    'sequence' => $sequence->name,
                    'pupils' => (int) $pair->pupils,
                ];
            }
        }

        return $units;
    }

    /**
     * @param  Collection<int, ClassSection>  $sections
     * @param  Collection<int, Sequence>  $sequences
     * @return Collection<string, MarksSubmission>
     */
    private function submissions(Collection $sections, Collection $sequences): Collection
    {
        if ($sections->isEmpty() || $sequences->isEmpty()) {
            return collect();
        }

        return MarksSubmission::whereIn('class_section_id', $sections->pluck('id'))
            ->whereIn('sequence_id', $sequences->pluck('id'))
            ->get()
            ->keyBy(fn (MarksSubmission $s) => $s->class_section_id.':'.$s->subject_id.':'.$s->sequence_id);
    }

    /**
     * Expected submissions that are not finished, each with what is wrong.
     *
     * A submission only counts as done once it is approved or published. Marks
     * sitting in draft are the teacher's working copy; marks merely submitted
     * are waiting on someone.
     *
     * @param  Collection<int, array<string, mixed>>  $expected
     * @param  Collection<string, MarksSubmission>  $submissions
     * @return Collection<int, array<string, mixed>>
     */
    private function pendingUnits(Collection $expected, Collection $submissions): Collection
    {
        return $expected->map(function (array $unit, string $key) use ($submissions) {
            $submission = $submissions->get($key);

            [$state, $entered, $total] = match (true) {
                $submission === null => ['not_started', 0, $unit['pupils']],
                default => [$submission->status, (int) $submission->marks_entered, (int) ($submission->total_students ?: $unit['pupils'])],
            };

            if (in_array($state, ['approved', 'published'], true)) {
                return null;
            }

            return $unit + [
                'state' => $state,
                'entered' => $entered,
                'total' => $total,
                'label' => match ($state) {
                    'not_started' => __('No marks entered'),
                    'draft' => __('Still with the teacher'),
                    'submitted' => __('Waiting for approval'),
                    'returned' => __('Returned to the teacher'),
                    default => $state,
                },
            ];
        })->filter()->values();
    }

    /**
     * Pupils with no mark at all this term.
     *
     * Distinct from a missing submission: this one catches the pupil who was
     * admitted late, or whose subject registration was never done, and who
     * would otherwise be ranked on nothing.
     *
     * @param  Collection<int, ClassSection>  $sections
     * @param  Collection<int, Sequence>  $sequences
     * @return Collection<int, array<string, mixed>>
     */
    private function studentsWithoutMarks(Collection $sections, Collection $sequences): Collection
    {
        if ($sections->isEmpty() || $sequences->isEmpty()) {
            return collect();
        }

        return collect(DB::table('student_enrollments')
            ->join('students', 'student_enrollments.student_id', '=', 'students.id')
            ->join('class_sections', 'student_enrollments.class_section_id', '=', 'class_sections.id')
            ->whereIn('student_enrollments.class_section_id', $sections->pluck('id'))
            ->where('student_enrollments.status', 'active')
            ->whereNotExists(function ($query) use ($sequences) {
                $query->select(DB::raw(1))
                    ->from('marks')
                    ->whereColumn('marks.student_enrollment_id', 'student_enrollments.id')
                    ->whereIn('marks.sequence_id', $sequences->pluck('id'));
            })
            ->select(
                'students.first_name', 'students.last_name', 'students.student_id as matricule',
                'class_sections.name as class_name'
            )
            ->orderBy('class_sections.name')
            ->orderBy('students.last_name')
            ->get())
            ->map(fn ($row) => [
                'student' => trim($row->first_name.' '.$row->last_name),
                'matricule' => $row->matricule,
                'class' => $row->class_name,
            ]);
    }

    /**
     * @param  Collection<int, ClassSection>  $sections
     * @return array<string, int>
     */
    private function resultCounts(int $termId, Collection $sections): array
    {
        if ($sections->isEmpty()) {
            return ['enrolled' => 0, 'generated' => 0, 'published' => 0];
        }

        $enrolled = DB::table('student_enrollments')
            ->whereIn('class_section_id', $sections->pluck('id'))
            ->where('status', 'active')
            ->count();

        $base = TermResult::query()
            ->where('term_results.term_id', $termId)
            ->join('student_enrollments', 'term_results.student_enrollment_id', '=', 'student_enrollments.id')
            ->whereIn('student_enrollments.class_section_id', $sections->pluck('id'));

        return [
            'enrolled' => $enrolled,
            'generated' => (clone $base)->count(),
            'published' => (clone $base)->where('term_results.is_published', true)->count(),
        ];
    }

    /**
     * @param  Collection<int, Sequence>  $sequences
     * @param  Collection<int, array<string, mixed>>  $expected
     * @param  Collection<int, array<string, mixed>>  $pending
     * @param  Collection<int, array<string, mixed>>  $withoutMarks
     * @param  array<string, int>  $results
     * @return array<int, array<string, mixed>>
     */
    private function checks(
        Collection $sequences,
        Collection $expected,
        Collection $pending,
        Collection $withoutMarks,
        array $results
    ): array {
        $checks = [];

        $checks[] = $sequences->isEmpty()
            ? $this->check('sequences', __('Sequences'), self::BLOCKER, __('This term has no sequences, so there is nothing to average.'))
            : $this->check('sequences', __('Sequences'), self::READY, __(':count sequence(s) set up.', ['count' => $sequences->count()]));

        // A sequence with no weight is silently treated as 1 by the calculator,
        // which is a decision the school has not actually made.
        $unweighted = $sequences->filter(fn (Sequence $s) => ! $s->weight || (float) $s->weight <= 0);
        if ($unweighted->isNotEmpty()) {
            $checks[] = $this->check('weights', __('Sequence weights'), self::WARNING, __(
                ':list have no weight and will each count as 1.',
                ['list' => $unweighted->pluck('name')->join(', ')]
            ));
        }

        if ($expected->isEmpty()) {
            $checks[] = $this->check('subjects', __('Subject registration'), self::BLOCKER,
                __('No pupil in scope is registered for any subject, so no marks are expected.'));
        }

        $notStarted = $pending->where('state', 'not_started');
        if ($notStarted->isNotEmpty()) {
            $checks[] = $this->check('not_started', __('Marks not entered'), self::BLOCKER, __(
                ':count subject-sequence(s) have no marks at all.',
                ['count' => $notStarted->count()]
            ));
        }

        $awaiting = $pending->whereIn('state', ['draft', 'submitted', 'returned']);
        if ($awaiting->isNotEmpty()) {
            $checks[] = $this->check('unapproved', __('Marks not approved'), self::BLOCKER, __(
                ':count subject-sequence(s) are entered but not approved, so they will not count.',
                ['count' => $awaiting->count()]
            ));
        }

        if ($expected->isNotEmpty() && $pending->isEmpty()) {
            $checks[] = $this->check('marks', __('Marks'), self::READY,
                __('Every expected subject and sequence is approved.'));
        }

        if ($withoutMarks->isNotEmpty()) {
            $checks[] = $this->check('no_marks', __('Pupils with no marks'), self::BLOCKER, __(
                ':count pupil(s) have no mark this term and would be ranked on nothing.',
                ['count' => $withoutMarks->count()]
            ));
        }

        $missingResults = max(0, $results['enrolled'] - $results['generated']);
        if ($missingResults > 0) {
            $checks[] = $this->check('results', __('Results generated'), self::BLOCKER, __(
                ':count of :total pupil(s) have no result for this term yet.',
                ['count' => $missingResults, 'total' => $results['enrolled']]
            ));
        } elseif ($results['enrolled'] > 0) {
            $checks[] = $this->check('results', __('Results generated'), self::READY,
                __('All :count pupil(s) have a result.', ['count' => $results['enrolled']]));
        }

        $unpublished = max(0, $results['generated'] - $results['published']);
        if ($unpublished > 0) {
            $checks[] = $this->check('publishing', __('Publishing'), self::WARNING, __(
                ':count result(s) are generated but not published, so parents cannot see them.',
                ['count' => $unpublished]
            ));
        } elseif ($results['published'] > 0) {
            $checks[] = $this->check('publishing', __('Publishing'), self::READY,
                __('All :count result(s) are published.', ['count' => $results['published']]));
        }

        return $checks;
    }

    /** @return array<string, string> */
    private function check(string $key, string $label, string $status, string $detail): array
    {
        return compact('key', 'label', 'status', 'detail');
    }

    /** @param array<int, array<string, mixed>> $checks */
    private function verdict(array $checks): string
    {
        foreach ([self::BLOCKER, self::WARNING] as $level) {
            foreach ($checks as $check) {
                if ($check['status'] === $level) {
                    return $level;
                }
            }
        }

        return self::READY;
    }

    /**
     * Readiness class by class, so the work can be chased with the right person.
     *
     * @param  Collection<int, ClassSection>  $sections
     * @param  Collection<int, array<string, mixed>>  $expected
     * @param  Collection<int, array<string, mixed>>  $pending
     * @param  array<string, int>  $results
     * @return array<int, array<string, mixed>>
     */
    private function byClass(Collection $sections, Collection $expected, Collection $pending, array $results): array
    {
        return $sections->map(function (ClassSection $section) use ($expected, $pending) {
            $mine = $expected->where('class_section_id', $section->id);
            $outstanding = $pending->where('class_section_id', $section->id);

            return [
                'id' => $section->id,
                'name' => $section->name,
                'form' => $section->form?->name,
                'expected' => $mine->count(),
                'pending' => $outstanding->count(),
                'done' => max(0, $mine->count() - $outstanding->count()),
                'percent' => $mine->count() > 0
                    ? round((($mine->count() - $outstanding->count()) / $mine->count()) * 100, 1)
                    : null,
                'subjects_outstanding' => $outstanding->pluck('subject')->unique()->sort()->values()->all(),
            ];
        })->sortBy('percent')->values()->all();
    }
}
