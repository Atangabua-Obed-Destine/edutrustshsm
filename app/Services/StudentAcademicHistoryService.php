<?php

namespace App\Services;

use App\Models\ClassCouncilEntry;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\TermResult;
use Illuminate\Support\Collection;

/**
 * One pupil, their whole way through the school.
 *
 * Report cards exist per term and nothing ever put them side by side, so the
 * two questions actually asked — "how has this child been doing?" at a parent
 * meeting, and "what did they do here?" on a transfer request — could only be
 * answered by opening six documents and holding them in your head.
 *
 * Years are built from enrolments rather than from results, so a year the
 * pupil was here but has no published result still appears, as a year with a
 * gap in it. Dropping it would quietly rewrite their record.
 */
class StudentAcademicHistoryService
{
    /**
     * @return array<string, mixed>
     */
    public function forStudent(Student $student): array
    {
        $enrollments = $this->enrollments($student);
        $results = $this->results($student);
        $councils = $this->councilDecisions($student);

        $years = $enrollments->map(fn (StudentEnrollment $enrollment) => $this->year($enrollment, $results, $councils))
            ->values()
            ->all();

        return [
            'years' => $years,
            'summary' => $this->summary($years),
            'subjects' => $this->subjectHistory($results),
            'trend' => $this->trend($years),
            'pass_mark' => (float) (SchoolSetting::current()?->pass_mark ?: 10),
        ];
    }

    /** @return Collection<int, StudentEnrollment> */
    private function enrollments(Student $student): Collection
    {
        return StudentEnrollment::where('student_id', $student->id)
            ->with(['academicSession', 'classSection.form', 'stream'])
            ->get()
            ->sortBy(fn (StudentEnrollment $e) => $e->academicSession?->start_date ?? $e->enrollment_date)
            ->values();
    }

    /** @return Collection<int, TermResult> */
    private function results(Student $student): Collection
    {
        return TermResult::query()
            ->whereHas('enrollment', fn ($q) => $q->where('student_id', $student->id))
            ->with(['term', 'enrollment.academicSession', 'subjectResults.subject:id,name'])
            ->get()
            ->sortBy(fn (TermResult $r) => [$r->enrollment?->academicSession?->start_date, $r->term?->term_number])
            ->values();
    }

    /**
     * What each class council decided about this pupil, by term.
     *
     * @return Collection<int, ClassCouncilEntry> keyed by term id
     */
    private function councilDecisions(Student $student): Collection
    {
        return ClassCouncilEntry::where('student_id', $student->id)
            ->with('council.term')
            ->get()
            ->keyBy(fn (ClassCouncilEntry $entry) => $entry->council?->term_id);
    }

    /**
     * One academic year: the class, the terms within it, and the year's outcome.
     *
     * @param  Collection<int, TermResult>  $results
     * @param  Collection<int, ClassCouncilEntry>  $councils
     * @return array<string, mixed>
     */
    private function year(StudentEnrollment $enrollment, Collection $results, Collection $councils): array
    {
        $terms = $results
            ->filter(fn (TermResult $r) => $r->student_enrollment_id === $enrollment->id)
            ->map(fn (TermResult $r) => [
                'term' => $r->term?->name,
                'term_number' => $r->term?->term_number,
                'average' => $r->term_average !== null ? (float) $r->term_average : null,
                'rank' => $r->class_rank,
                'of' => $r->total_students,
                'class_average' => $r->class_average !== null ? (float) $r->class_average : null,
                'grade' => $r->overall_grade,
                'conduct' => $r->conduct,
                'present' => $r->days_present,
                'absent' => $r->days_absent,
                'school_days' => $r->total_school_days,
                'teacher_remark' => $r->class_teacher_remark,
                'principal_remark' => $r->principal_remark,
                'published' => (bool) $r->is_published,
                'council' => $this->councilFor($councils, $r->term_id),
                'result_id' => $r->id,
            ])
            ->sortBy('term_number')
            ->values()
            ->all();

        return [
            'session' => $enrollment->academicSession?->name,
            'class' => $enrollment->classSection?->name,
            'form' => $enrollment->classSection?->form?->name,
            'stream' => $enrollment->stream?->name,
            'status' => $enrollment->status,
            'final_average' => $enrollment->final_average !== null ? (float) $enrollment->final_average : null,
            'final_rank' => $enrollment->final_rank,
            'terms' => $terms,
            // Said plainly rather than left as an empty row: a year with no
            // published result is a fact about the record, not a rendering gap.
            'has_results' => count($terms) > 0,
        ];
    }

    /** @param Collection<int, ClassCouncilEntry> $councils */
    private function councilFor(Collection $councils, ?int $termId): ?array
    {
        $entry = $termId ? $councils->get($termId) : null;

        if (! $entry || ! $entry->council?->isConcluded()) {
            return null;
        }

        return [
            'award' => $entry->award,
            'label' => $entry->awardLabel(),
            'classes' => $entry->awardClasses(),
            'remark' => $entry->remark,
        ];
    }

    /**
     * The headline facts a parent meeting opens with.
     *
     * @param  array<int, array<string, mixed>>  $years
     * @return array<string, mixed>
     */
    private function summary(array $years): array
    {
        $averages = collect($years)
            ->flatMap(fn (array $year) => collect($year['terms'])->pluck('average'))
            ->filter()
            ->map(fn ($v) => (float) $v);

        $attendance = collect($years)->flatMap(fn (array $year) => $year['terms']);
        $present = $attendance->sum('present');
        $days = $attendance->sum('school_days');

        return [
            'years' => count($years),
            'terms' => $averages->count(),
            'average' => $averages->isNotEmpty() ? round($averages->avg(), 2) : null,
            'best' => $averages->max(),
            'worst' => $averages->min(),
            'attendance_rate' => $days > 0 ? round(($present / $days) * 100, 1) : null,
            // Only meaningful once there is something to compare against.
            'direction' => $averages->count() >= 2
                ? round($averages->last() - $averages->first(), 2)
                : null,
        ];
    }

    /**
     * Every subject the pupil has sat, with how it has gone over time.
     *
     * Sorted weakest first: the point of the list is to find what needs work,
     * not to admire the strong ones.
     *
     * @param  Collection<int, TermResult>  $results
     * @return array<int, array<string, mixed>>
     */
    private function subjectHistory(Collection $results): array
    {
        $rows = [];

        foreach ($results as $result) {
            foreach ($result->subjectResults as $subjectResult) {
                if ($subjectResult->term_average === null) {
                    continue;
                }

                $name = $subjectResult->subject?->name ?? __('Unknown subject');
                $rows[$name] ??= ['name' => $name, 'scores' => []];
                $rows[$name]['scores'][] = (float) $subjectResult->term_average;
            }
        }

        return collect($rows)
            ->map(function (array $row) {
                $scores = collect($row['scores']);

                return [
                    'name' => $row['name'],
                    'terms' => $scores->count(),
                    'average' => round($scores->avg(), 2),
                    'best' => $scores->max(),
                    'worst' => $scores->min(),
                    'first' => $scores->first(),
                    'last' => $scores->last(),
                    'change' => $scores->count() >= 2 ? round($scores->last() - $scores->first(), 2) : null,
                ];
            })
            ->sortBy('average')
            ->values()
            ->all();
    }

    /**
     * Every term in order, for the line.
     *
     * @param  array<int, array<string, mixed>>  $years
     * @return array<int, array<string, mixed>>
     */
    private function trend(array $years): array
    {
        $points = [];

        foreach ($years as $year) {
            foreach ($year['terms'] as $term) {
                $points[] = [
                    'label' => trim(($year['session'] ?? '').' · '.($term['term'] ?? ''), ' ·'),
                    'average' => $term['average'],
                    'class_average' => $term['class_average'],
                ];
            }
        }

        return $points;
    }
}
