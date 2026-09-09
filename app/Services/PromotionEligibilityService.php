<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Term;
use App\Models\TermResult;
use Illuminate\Support\Collection;

/**
 * Decides whether a student may move up a year, and why.
 *
 * A student has one enrollment per TERM, so their year is spread across
 * several rows. This gathers them, computes the annual average as the mean of
 * the term averages, and refuses to promote on incomplete results.
 *
 * Promotion previously had no gate at all: a student with no marks, or with
 * marks still sitting in draft, could be moved up. The recommendation itself
 * was also always null, because it read a term_results column that does not
 * exist.
 */
class PromotionEligibilityService
{
    /**
     * Assess every student in a class section for a session.
     *
     * @return Collection<int, object>
     */
    public function forClass(int $classSectionId, int $sessionId): Collection
    {
        $enrollments = StudentEnrollment::where('class_section_id', $classSectionId)
            ->where('academic_session_id', $sessionId)
            ->where('status', 'active')
            ->with('student:id,student_id,first_name,last_name')
            ->get();

        // One enrollment per student per term: collapse to the student, then
        // assess the year as a whole.
        return $enrollments
            ->groupBy('student_id')
            ->map(fn (Collection $rows) => $this->assess($rows->sortByDesc('term_id')->first(), $sessionId))
            ->values();
    }

    /**
     * Assess one student's year.
     *
     * @return object{
     *     enrollment: StudentEnrollment, student: ?Student, average: ?float,
     *     terms_completed: int, terms_expected: int, eligible: bool,
     *     recommendation: string, reason: ?string
     * }
     */
    public function assess(StudentEnrollment $enrollment, int $sessionId): object
    {
        $threshold = (float) (SchoolSetting::current()?->promotion_threshold ?? 10);
        $expected = max(1, Term::count());

        $results = $this->resultsFor($enrollment->student_id, $sessionId);
        $averages = $results->pluck('term_average')
            ->filter(fn ($v) => $v !== null)
            ->map(fn ($v) => (float) $v);

        // Mean of the term averages. A term with no result is missing, not a
        // zero — scoring it zero would fail students for the school's own
        // incomplete data entry.
        $annual = $averages->isNotEmpty() ? round($averages->avg(), 2) : null;

        $complete = $averages->count() >= $expected;

        [$eligible, $recommendation, $reason] = match (true) {
            $annual === null => [false, 'repeat', __('No results have been generated for this student.')],
            ! $complete => [false, 'repeat', __('Results are missing for :missing of :total terms.', [
                'missing' => $expected - $averages->count(),
                'total' => $expected,
            ])],
            $annual < $threshold => [false, 'repeat', __('Annual average :avg is below the pass threshold of :threshold.', [
                'avg' => number_format($annual, 2),
                'threshold' => number_format($threshold, 2),
            ])],
            default => [true, 'promote', null],
        };

        return (object) [
            'enrollment' => $enrollment,
            'student' => $enrollment->student,
            'average' => $annual,
            'terms_completed' => $averages->count(),
            'terms_expected' => $expected,
            'eligible' => $eligible,
            'recommendation' => $recommendation,
            'reason' => $reason,
        ];
    }

    /**
     * Write the annual average and rank onto the year's enrollments.
     *
     * final_average and final_rank have existed on student_enrollments, been
     * displayed on the student page, and been written by nothing at all. This
     * is the session-level aggregation the term results never rolled up into.
     *
     * @return int students updated
     */
    public function recordAnnualResults(int $classSectionId, int $sessionId): int
    {
        $assessments = $this->forClass($classSectionId, $sessionId)
            ->filter(fn ($a) => $a->average !== null)
            ->sortByDesc('average')
            ->values();

        $rank = 0;
        $previous = null;

        foreach ($assessments as $index => $assessment) {
            if ($previous === null || abs($assessment->average - $previous) >= 0.005) {
                $rank = $index + 1;
            }
            $previous = $assessment->average;

            // Every term row of that student's year carries the annual figures,
            // so the record is the same whichever term you open.
            StudentEnrollment::where('student_id', $assessment->enrollment->student_id)
                ->where('academic_session_id', $sessionId)
                ->update(['final_average' => $assessment->average, 'final_rank' => $rank]);
        }

        return $assessments->count();
    }

    /** The term a promoted student should land in: the first of the year. */
    public function landingTerm(): ?Term
    {
        return Term::orderBy('term_number')->first();
    }

    /** The session following a given one. */
    public function nextSession(AcademicSession $session): ?AcademicSession
    {
        return AcademicSession::where('start_date', '>', $session->end_date)
            ->orderBy('start_date')
            ->first();
    }

    /** @return Collection<int, TermResult> */
    private function resultsFor(int $studentId, int $sessionId): Collection
    {
        return TermResult::whereHas(
            'enrollment',
            fn ($q) => $q->where('student_id', $studentId)->where('academic_session_id', $sessionId)
        )->get();
    }
}
