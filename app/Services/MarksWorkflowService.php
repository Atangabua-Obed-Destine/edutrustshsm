<?php

namespace App\Services;

use App\Models\Mark;
use App\Models\MarksSubmission;
use App\Models\MarksWorkflowLog;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The mark approval workflow: draft → submitted → approved → published.
 *
 * Every entry point goes through here. Previously the transition rules existed
 * as a map inside one bulk action and as ad-hoc `if` checks elsewhere, while
 * MarksController::submit, approve and returnMarks had NO state check at all —
 * a draft could be approved directly, and a published submission re-approved.
 * Now that report cards only count approved marks, that mattered.
 *
 * Marks and their submission move together, so the two can no longer
 * disagree about what state a class's results are in.
 */
class MarksWorkflowService
{
    /**
     * Which states each transition may be entered FROM.
     *
     * @var array<string, array<int, string>>
     */
    public const TRANSITIONS = [
        'submitted' => ['draft', 'returned'],
        'approved' => ['submitted'],
        'published' => ['approved'],
        // Returning sends work back to the teacher, from either review stage.
        'returned' => ['submitted', 'approved'],
        // Unpublishing walks one step back, no further.
        'unpublished' => ['published'],
    ];

    /** The permission each transition requires. */
    public const PERMISSIONS = [
        'submitted' => 'marks-entry.enter',
        'approved' => 'marks-entry.edit',
        'returned' => 'marks-entry.edit',
        'published' => 'exam-publishing.publish',
        'unpublished' => 'exam-publishing.unpublish',
    ];

    /** Whether a submission may make a given transition right now. */
    public function can(MarksSubmission $submission, string $transition): bool
    {
        return in_array($submission->status, self::TRANSITIONS[$transition] ?? [], true);
    }

    /**
     * Apply a transition, moving the submission and its marks together.
     *
     * @throws RuntimeException when the transition is not allowed from here
     */
    public function apply(MarksSubmission $submission, string $transition, ?string $note = null): MarksSubmission
    {
        if (! isset(self::TRANSITIONS[$transition])) {
            throw new RuntimeException(__('Unknown transition.'));
        }

        if (! $this->can($submission, $transition)) {
            throw new RuntimeException(__(
                'Marks cannot go from :from to :to.',
                ['from' => __(ucfirst($submission->status)), 'to' => __(ucfirst($this->targetStatus($transition)))]
            ));
        }

        // Returning must say why; the teacher has to know what to fix.
        if ($transition === 'returned' && blank($note)) {
            throw new RuntimeException(__('Say what needs correcting before returning marks.'));
        }

        return DB::transaction(function () use ($submission, $transition, $note) {
            $from = $submission->status;
            $to = $this->targetStatus($transition);

            $submission->update($this->submissionAttributes($to, $note));
            $this->marksFor($submission)->update($this->markAttributes($to, $note));

            MarksWorkflowLog::create([
                'marks_submission_id' => $submission->id,
                'from_status' => $from,
                'to_status' => $to,
                'user_id' => auth()->id(),
                'note' => $note,
                'created_at' => now(),
            ]);

            return $submission->refresh();
        });
    }

    /**
     * Apply a transition to many submissions, skipping those that cannot make it.
     *
     * @param  iterable<MarksSubmission>  $submissions
     * @return array{applied: int, skipped: array<int, string>}
     */
    public function applyMany(iterable $submissions, string $transition, ?string $note = null): array
    {
        $applied = 0;
        $skipped = [];

        foreach ($submissions as $submission) {
            if (! $this->can($submission, $transition)) {
                $skipped[] = $submission->subject?->name ?? '#'.$submission->id;
                continue;
            }

            $this->apply($submission, $transition, $note);
            $applied++;
        }

        return ['applied' => $applied, 'skipped' => $skipped];
    }

    /** The status a transition lands on. */
    public function targetStatus(string $transition): string
    {
        // "unpublished" is the only transition whose name is not its status.
        return $transition === 'unpublished' ? 'approved' : $transition;
    }

    /**
     * Whether marks may still be edited.
     *
     * Saving used to reset marks to draft even after publication, leaving the
     * submission saying "published" while its marks said "draft".
     */
    public function isEditable(MarksSubmission $submission): bool
    {
        return in_array($submission->status, ['draft', 'returned'], true);
    }

    /** @return array<string, mixed> */
    private function submissionAttributes(string $to, ?string $note): array
    {
        $attributes = ['status' => $to];

        if ($to === 'submitted') {
            $attributes['submitted_at'] = now();
        }

        if ($to === 'approved') {
            $attributes['approved_by'] = auth()->id();
            $attributes['approved_at'] = now();
        }

        if ($note !== null) {
            $attributes['admin_comment'] = $note;
        }

        return $attributes;
    }

    /** @return array<string, mixed> */
    private function markAttributes(string $to, ?string $note): array
    {
        $attributes = ['status' => $to];

        if ($to === 'submitted') {
            $attributes['submitted_at'] = now();
        }

        if ($to === 'approved') {
            $attributes['approved_by'] = auth()->id();
            $attributes['approved_at'] = now();
        }

        if ($note !== null) {
            $attributes['admin_comment'] = $note;
        }

        return $attributes;
    }

    /** The marks a submission covers. */
    private function marksFor(MarksSubmission $submission)
    {
        return Mark::where('subject_id', $submission->subject_id)
            ->where('sequence_id', $submission->sequence_id)
            ->whereHas('enrollment', fn ($q) => $q->where('class_section_id', $submission->class_section_id));
    }
}
