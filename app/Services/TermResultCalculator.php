<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\ClassSection;
use App\Models\GradeScale;
use App\Models\Mark;
use App\Models\SchoolSetting;
use App\Models\Sequence;
use App\Models\StudentEnrollment;
use App\Models\SubjectTermResult;
use App\Models\Term;
use App\Models\TermResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The single computation behind a term's results.
 *
 * This existed twice — once in ReportCardController::generate and again in
 * ExamPublishingController::index — with three differences, so the number a
 * teacher approved on the publishing screen was routinely not the number that
 * reached the report card:
 *
 *   1. the subject universe (registered subjects vs every subject on the form),
 *   2. the coefficient source (the student's pivot vs MAX across streams),
 *   3. whether unregistered subjects fed the overall average (they did, on the
 *      publishing screen, which is simply wrong).
 *
 * There is now one calculation. `compute()` returns it; `persist()` writes it.
 * Exam Publishing calls compute() alone, so a preview is by construction the
 * same arithmetic that will be saved.
 *
 * A student's own registered subjects are the universe: those are the subjects
 * they actually sit. Subjects on the form they are not registered for are
 * reported separately and never affect an average.
 */
class TermResultCalculator
{
    /**
     * Compute results for a class section and term without writing anything.
     *
     * @return array{
     *     sequences: Collection,
     *     students: Collection,
     *     class: array{average: float, highest: float, lowest: float, count: int},
     *     pass_mark: float
     * }
     */
    public function compute(ClassSection $classSection, Term $term, int $sessionId): array
    {
        $sequences = $this->sequencesFor($classSection->form_id, $term->id);
        $passMark = (float) (SchoolSetting::current()?->pass_mark ?? 10);

        $enrollments = StudentEnrollment::where('class_section_id', $classSection->id)
            ->where('academic_session_id', $sessionId)
            ->where('term_id', $term->id)
            ->whereIn('status', ['active', 'completed'])
            ->with([
                'student:id,student_id,first_name,last_name',
                'studentSubjects.subject.forms' => fn ($q) => $q->where('form_id', $classSection->form_id),
            ])
            ->get();

        // One query for every mark in scope, rather than one per
        // (student x subject x sequence) — that was ~1,800 queries for a class
        // of 60 with 10 subjects.
        $marks = $this->marksFor($enrollments->pluck('id'), $sequences->pluck('id'), $passMark);

        $grades = GradeScale::orderByDesc('min_mark')->get();
        $teachers = $this->teacherNames($classSection);

        $students = $enrollments->map(fn (StudentEnrollment $enrollment) => $this->computeStudent(
            $enrollment,
            $sequences,
            $marks->get($enrollment->id, collect()),
            $grades,
            $teachers
        ));

        return [
            'sequences' => $sequences,
            'students' => $this->rank($students),
            'class' => $this->classStats($students),
            'pass_mark' => $passMark,
        ];
    }

    /**
     * Compute and save. Ranks and class statistics are written too.
     *
     * @return int number of students processed
     */
    public function persist(ClassSection $classSection, Term $term, int $sessionId): int
    {
        $result = $this->compute($classSection, $term, $sessionId);

        if ($result['students']->isEmpty()) {
            return 0;
        }

        $attendance = $this->attendanceFor($result['students']->pluck('enrollment_id'), $term);

        DB::transaction(function () use ($result, $term, $attendance) {
            foreach ($result['students'] as $student) {
                $termResult = TermResult::firstOrNew([
                    'student_enrollment_id' => $student->enrollment_id,
                    'term_id' => $term->id,
                ]);

                $days = $attendance[$student->enrollment_id] ?? ['present' => 0, 'absent' => 0];

                $termResult->fill([
                    'total_weighted_score' => $student->total_weighted,
                    'total_coefficient' => $student->total_coefficient,
                    'term_average' => $student->average,
                    'overall_grade' => $student->grade,
                    'class_rank' => $student->rank,
                    'total_students' => $result['class']['count'],
                    'class_average' => $result['class']['average'],
                    'highest_average' => $result['class']['highest'],
                    'lowest_average' => $result['class']['lowest'],
                    'days_present' => $days['present'],
                    'days_absent' => $days['absent'],
                    'total_school_days' => $days['present'] + $days['absent'],
                    // is_published is never touched here: regenerating after a
                    // mark correction must not revoke parent access.
                ]);
                $termResult->save();

                foreach ($student->subjects as $subject) {
                    SubjectTermResult::updateOrCreate(
                        ['term_result_id' => $termResult->id, 'subject_id' => $subject['subject_id']],
                        $this->subjectRow($subject)
                    );
                }
            }

            $this->rankSubjects($result['students']->pluck('enrollment_id'), $term);
        });

        return $result['students']->count();
    }

    /** Sequences mapped to this form and term, in order. */
    public function sequencesFor(int $formId, int $termId): Collection
    {
        $ids = DB::table('form_sequence')
            ->where('form_id', $formId)
            ->where('term_id', $termId)
            ->distinct()
            ->pluck('sequence_id');

        return Sequence::whereIn('id', $ids)->orderBy('sequence_number')->orderBy('id')->get();
    }

    /**
     * Marks in scope, keyed by enrollment then "subject:sequence".
     *
     * Only marks that have cleared the approval workflow count — without that
     * the draft -> submitted -> approved -> published pipeline has no effect on
     * results at all.
     */
    private function marksFor(Collection $enrollmentIds, Collection $sequenceIds, float $passMark): Collection
    {
        if ($enrollmentIds->isEmpty() || $sequenceIds->isEmpty()) {
            return collect();
        }

        return Mark::whereIn('student_enrollment_id', $enrollmentIds)
            ->whereIn('sequence_id', $sequenceIds)
            ->whereIn('status', Mark::REPORTABLE_STATUSES)
            ->get(['student_enrollment_id', 'subject_id', 'sequence_id', 'score', 'is_absent'])
            ->groupBy('student_enrollment_id')
            ->map(fn ($rows) => $rows->keyBy(fn ($m) => $m->subject_id.':'.$m->sequence_id));
    }

    /** @return array<int, string> subject_id => teacher name */
    private function teacherNames(ClassSection $classSection): array
    {
        return $classSection->teacherAssignments()
            ->with('teacher:id,first_name,last_name')
            ->get()
            ->mapWithKeys(fn ($a) => [$a->subject_id => $a->teacher?->full_name])
            ->all();
    }

    /** @param array<int, string> $teachers */
    private function computeStudent(
        StudentEnrollment $enrollment,
        Collection $sequences,
        Collection $marks,
        Collection $grades,
        array $teachers
    ): object {
        $subjects = [];
        $totalWeighted = 0.0;
        $totalCoefficient = 0.0;

        foreach ($enrollment->studentSubjects as $studentSubject) {
            $subject = $studentSubject->subject;

            if (! $subject) {
                continue;
            }

            // The coefficient the student is actually registered under, falling
            // back to the form's. Taking MAX across streams — as the publishing
            // screen did — silently changes the weighting for streamed subjects.
            $coefficient = (float) ($studentSubject->coefficient
                ?? $subject->forms->first()?->pivot?->coefficient
                ?? 1);

            $scores = [];
            $weights = [];
            $weightedSum = 0.0;
            $totalWeight = 0.0;

            foreach ($sequences as $sequence) {
                $mark = $marks->get($subject->id.':'.$sequence->id);
                $score = ($mark && ! $mark->is_absent && $mark->score !== null)
                    ? (float) $mark->score
                    : null;

                $scores[$sequence->id] = $score;
                $weight = (float) ($sequence->weight ?: 1);
                $weights[$sequence->id] = $weight;

                if ($score !== null) {
                    $weightedSum += $score * $weight;
                    $totalWeight += $weight;
                }
            }

            $average = $totalWeight > 0 ? round($weightedSum / $totalWeight, 2) : null;
            $weightedScore = $average !== null ? round($average * $coefficient, 2) : null;

            if ($average !== null) {
                $totalWeighted += $weightedScore;
                $totalCoefficient += $coefficient;
            }

            $subjects[] = [
                'subject_id' => $subject->id,
                'subject_name' => $subject->name,
                'coefficient' => $coefficient,
                'scores' => $scores,
                'weights' => $weights,
                'term_average' => $average,
                'weighted_score' => $weightedScore,
                'grade' => $average !== null ? $this->gradeFor($average, $grades) : '-',
                'teacher_name' => $teachers[$subject->id] ?? null,
            ];
        }

        $average = $totalCoefficient > 0 ? round($totalWeighted / $totalCoefficient, 2) : null;

        return (object) [
            'enrollment_id' => $enrollment->id,
            'student' => $enrollment->student,
            'subjects' => $subjects,
            'total_weighted' => round($totalWeighted, 2),
            'total_coefficient' => $totalCoefficient,
            'average' => $average,
            'grade' => $average !== null ? $this->gradeFor($average, $grades) : '-',
            'rank' => null,
        ];
    }

    /**
     * Dense-rank students by average.
     *
     * Students with no marks at all are excluded from ranking entirely rather
     * than being ranked last on an average of zero — previously the statistics
     * filtered them out but the ranking did not, so class_rank could exceed
     * total_students.
     */
    private function rank(Collection $students): Collection
    {
        $ranked = $students->filter(fn ($s) => $s->average !== null)
            ->sortByDesc('average')
            ->values();

        $rank = 0;
        $previous = null;

        foreach ($ranked as $index => $student) {
            // Compared as floats: the old code used !== on mixed int/float, so
            // 0 !== 0.0 broke the tie chain.
            if ($previous === null || abs((float) $student->average - $previous) >= 0.005) {
                $rank = $index + 1;
            }

            $student->rank = $rank;
            $previous = (float) $student->average;
        }

        return $students;
    }

    /** @return array{average: float, highest: float, lowest: float, count: int} */
    private function classStats(Collection $students): array
    {
        $averages = $students->pluck('average')->filter(fn ($v) => $v !== null)->values();

        return [
            'average' => $averages->isNotEmpty() ? round($averages->avg(), 2) : 0.0,
            'highest' => $averages->isNotEmpty() ? round($averages->max(), 2) : 0.0,
            'lowest' => $averages->isNotEmpty() ? round($averages->min(), 2) : 0.0,
            'count' => $averages->count(),
        ];
    }

    /**
     * Shape one subject row for storage.
     *
     * sequence_1..3_score are kept populated for the existing report-card
     * views; sequence_scores carries every sequence, so a term with four or
     * more no longer loses the extras.
     *
     * @param  array<string, mixed>  $subject
     * @return array<string, mixed>
     */
    private function subjectRow(array $subject): array
    {
        $ordered = array_values($subject['scores']);

        return [
            'coefficient' => $subject['coefficient'],
            'sequence_1_score' => $ordered[0] ?? null,
            'sequence_2_score' => $ordered[1] ?? null,
            'sequence_3_score' => $ordered[2] ?? null,
            'sequence_scores' => $subject['scores'],
            // Snapshot: changing a weight or coefficient later must not silently
            // rewrite results that were already published.
            'resolved_weights' => [
                'sequences' => $subject['weights'],
                'coefficient' => $subject['coefficient'],
            ],
            'term_average' => $subject['term_average'],
            'weighted_score' => $subject['weighted_score'],
            'grade' => $subject['grade'],
            'teacher_name' => $subject['teacher_name'],
        ];
    }

    /** Dense-rank each subject across the class. */
    private function rankSubjects(Collection $enrollmentIds, Term $term): void
    {
        $termResultIds = TermResult::whereIn('student_enrollment_id', $enrollmentIds)
            ->where('term_id', $term->id)
            ->pluck('id');

        $rows = SubjectTermResult::whereIn('term_result_id', $termResultIds)
            ->whereNotNull('term_average')
            ->get()
            ->groupBy('subject_id');

        foreach ($rows as $subjectRows) {
            $sorted = $subjectRows->sortByDesc(fn ($r) => (float) $r->term_average)->values();
            $total = $sorted->count();

            $rank = 0;
            $previous = null;

            foreach ($sorted as $index => $row) {
                if ($previous === null || abs((float) $row->term_average - $previous) >= 0.005) {
                    $rank = $index + 1;
                }

                $row->update(['subject_rank' => $rank, 'subject_total_students' => $total]);
                $previous = (float) $row->term_average;
            }
        }
    }

    /** @return array<int, array{present: int, absent: int}> */
    private function attendanceFor(Collection $enrollmentIds, Term $term): array
    {
        $query = Attendance::whereIn('student_enrollment_id', $enrollmentIds)
            ->when(
                $term->start_date && $term->end_date,
                fn ($q) => $q->whereBetween('date', [$term->start_date, $term->end_date])
            );

        $counts = [];

        foreach ($query->get(['student_enrollment_id', 'status']) as $row) {
            $counts[$row->student_enrollment_id] ??= ['present' => 0, 'absent' => 0];

            if (in_array($row->status, ['present', 'late'], true)) {
                $counts[$row->student_enrollment_id]['present']++;
            } elseif ($row->status === 'absent') {
                $counts[$row->student_enrollment_id]['absent']++;
            }
        }

        return $counts;
    }

    private function gradeFor(float $score, Collection $grades): string
    {
        foreach ($grades as $grade) {
            if ($score >= (float) $grade->min_mark && $score <= (float) $grade->max_mark) {
                return $grade->grade;
            }
        }

        return '-';
    }
}
