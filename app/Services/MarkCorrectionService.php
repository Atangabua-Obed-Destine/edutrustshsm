<?php

namespace App\Services;

use App\Models\ClassSection;
use App\Models\GradeScale;
use App\Models\Mark;
use App\Models\MarkCorrection;
use App\Models\MarksSubmission;
use App\Models\MarksWorkflowLog;
use App\Models\SchoolSetting;
use App\Models\StudentEnrollment;
use App\Models\Term;
use App\Models\TermResult;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * One mark changed after the sheet was approved or published, the reference
 * system's way: the sheet does not go back to the teacher, but the change is
 * never silent. The old and the new mark, the reason and who made it are
 * kept; the sheet's figures are worked out again; and any report card already
 * calculated for the class is recalculated (staying published if it was), so
 * the parent never reads a card that disagrees with the mark.
 */
class MarkCorrectionService
{
    public const MIN_REASON = 10;

    /** How many terms' report cards the last correction recalculated. */
    public int $recalculatedTerms = 0;

    public function __construct(private TermResultCalculator $calculator) {}

    /**
     * @throws RuntimeException with a message for the person correcting
     */
    public function correct(MarksSubmission $submission, int $enrollmentId, ?float $score, bool $absent, string $reason): MarkCorrection
    {
        if (! in_array($submission->status, ['approved', 'published'], true)) {
            throw new RuntimeException(__('These marks have not been approved yet. Change them on the sheet itself.'));
        }

        if (mb_strlen(trim($reason)) < self::MIN_REASON) {
            throw new RuntimeException(__('Say why the mark is being corrected (at least :n characters).', ['n' => self::MIN_REASON]));
        }

        $max = (float) (SchoolSetting::current()?->max_mark ?? 20);
        if (! $absent && ($score === null || $score < 0 || $score > $max)) {
            throw new RuntimeException(__('The mark must be between 0 and :max, or the pupil marked absent.', ['max' => rtrim(rtrim(number_format($max, 2, '.', ''), '0'), '.')]));
        }

        $enrollment = StudentEnrollment::whereKey($enrollmentId)
            ->where('class_section_id', $submission->class_section_id)
            ->when($submission->academic_session_id, fn ($q) => $q->where('academic_session_id', $submission->academic_session_id))
            ->first();

        if (! $enrollment) {
            throw new RuntimeException(__('That pupil is not in this class.'));
        }

        $score = $absent ? null : round((float) $score, 2);

        $correction = DB::transaction(function () use ($submission, $enrollment, $score, $absent, $reason) {
            $mark = Mark::where('student_enrollment_id', $enrollment->id)
                ->where('subject_id', $submission->subject_id)
                ->where('sequence_id', $submission->sequence_id)
                ->lockForUpdate()
                ->first();

            $oldScore = $mark && ! $mark->is_absent ? $mark->score : null;
            $oldAbsent = (bool) $mark?->is_absent;

            if ($oldAbsent === $absent && ($absent || ($oldScore !== null && abs((float) $oldScore - (float) $score) < 0.001))) {
                throw new RuntimeException(__('That is already the mark on the sheet.'));
            }

            $mark ??= new Mark([
                'student_enrollment_id' => $enrollment->id,
                'subject_id' => $submission->subject_id,
                'sequence_id' => $submission->sequence_id,
                'entered_by' => auth()->id(),
            ]);

            $mark->fill([
                'score' => $score,
                'is_absent' => $absent,
                'grade' => $score === null ? null : $this->gradeFor($score),
                // The corrected mark keeps the sheet's standing.
                'status' => $submission->status,
                'approved_by' => $mark->approved_by ?? $submission->approved_by,
                'approved_at' => $mark->approved_at ?? $submission->approved_at,
            ])->save();

            $correction = MarkCorrection::create([
                'branch_id' => $submission->branch_id,
                'mark_id' => $mark->id,
                'marks_submission_id' => $submission->id,
                'old_score' => $oldScore,
                'old_absent' => $oldAbsent,
                'new_score' => $score,
                'new_absent' => $absent,
                'reason' => trim($reason),
                'corrected_by' => auth()->id(),
            ]);

            MarksWorkflowLog::create([
                'marks_submission_id' => $submission->id,
                'from_status' => $submission->status,
                'to_status' => $submission->status,
                'user_id' => auth()->id(),
                'note' => __('Mark corrected for :name: :old → :new. :reason', [
                    'name' => $enrollment->student?->full_name ?? '#'.$enrollment->id,
                    'old' => MarkCorrection::describe($oldScore, $oldAbsent),
                    'new' => MarkCorrection::describe($score, $absent),
                    'reason' => trim($reason),
                ]),
                'created_at' => now(),
            ]);

            $this->refreshSheetFigures($submission);

            return $correction;
        });

        $this->recalculatedTerms = $this->recalculateReportCards($submission);

        return $correction;
    }

    /** Count, average, highest, lowest and pass rate, from the marks as they now stand. */
    private function refreshSheetFigures(MarksSubmission $submission): void
    {
        $marks = Mark::where('subject_id', $submission->subject_id)
            ->where('sequence_id', $submission->sequence_id)
            ->whereHas('enrollment', fn ($q) => $q->where('class_section_id', $submission->class_section_id)
                ->when($submission->academic_session_id, fn ($q) => $q->where('academic_session_id', $submission->academic_session_id)))
            ->get(['score', 'is_absent']);

        $scores = $marks->where('is_absent', false)->whereNotNull('score')->pluck('score')->map(fn ($s) => (float) $s);
        $pass = (float) (SchoolSetting::current()?->pass_mark ?? 10);

        $submission->update([
            'marks_entered' => $marks->filter(fn ($m) => $m->is_absent || $m->score !== null)->count(),
            'class_average' => $scores->isEmpty() ? null : round($scores->avg(), 2),
            'highest_mark' => $scores->isEmpty() ? null : $scores->max(),
            'lowest_mark' => $scores->isEmpty() ? null : $scores->min(),
            'pass_rate' => $scores->isEmpty() ? null : round($scores->filter(fn ($s) => $s >= $pass)->count() / $scores->count() * 100, 2),
        ]);
    }

    /**
     * Recalculate the class's report cards for every term this sequence
     * counts in, where cards have already been calculated. A year recorded
     * from paper is never recalculated.
     */
    private function recalculateReportCards(MarksSubmission $submission): int
    {
        $class = ClassSection::find($submission->class_section_id);
        if (! $class || ! $submission->academic_session_id) {
            return 0;
        }

        $done = 0;

        $termIds = DB::table('form_sequence')
            ->where('form_id', $class->form_id)
            ->where('sequence_id', $submission->sequence_id)
            ->distinct()
            ->pluck('term_id');

        foreach (Term::whereIn('id', $termIds)->get() as $term) {
            $cards = TermResult::where('term_id', $term->id)
                ->whereHas('enrollment', fn ($q) => $q->where('class_section_id', $class->id)
                    ->where('academic_session_id', $submission->academic_session_id));

            if ($cards->exists() && ! (clone $cards)->where('source', 'recorded')->exists()) {
                $this->calculator->persist($class, $term, (int) $submission->academic_session_id);
                $done++;
            }
        }

        return $done;
    }

    private function gradeFor(float $score): ?string
    {
        // Scales are ordered highest first: the first band reached is the band.
        foreach (GradeScale::orderByDesc('min_mark')->get() as $band) {
            if ($score >= $band->min_mark) {
                return $band->grade;
            }
        }

        return null;
    }
}
