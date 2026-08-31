<?php

namespace App\Services;

use App\Models\GceCandidate;
use App\Models\GceRegistrationSession;
use App\Models\GceSubject;
use App\Models\Student;
use App\Models\StudentEnrollment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Entering candidates for a GCE series.
 *
 * The rules the Board actually enforces live here: a series has to be open, a
 * candidate has to carry a legal number of subjects for its level, and a
 * student sits a series once. Getting any of these wrong is discovered by the
 * Board weeks later, when it is expensive to fix, so they are refused up front.
 */
class GceRegistrationService
{
    /**
     * Students who may still be entered for this series.
     *
     * @return Collection<int, StudentEnrollment>
     */
    public function eligible(GceRegistrationSession $session, ?int $classSectionId = null): Collection
    {
        $formIds = $session->forms->pluck('id');

        // A series with no classes attached would otherwise silently offer the
        // whole school.
        if ($formIds->isEmpty()) {
            return collect();
        }

        $registered = $session->candidates()->pluck('student_id');

        return StudentEnrollment::query()
            ->where('academic_session_id', $session->academic_session_id)
            ->whereIn('status', ['active', 'completed'])
            ->whereHas('classSection', fn ($q) => $q->whereIn('form_id', $formIds))
            ->when($classSectionId, fn ($q) => $q->where('class_section_id', $classSectionId))
            ->whereNotIn('student_id', $registered)
            ->with(['student:id,student_id,first_name,last_name', 'classSection:id,name,form_id'])
            ->get()
            // A student has one enrollment per term; the candidate is the student.
            ->unique('student_id')
            ->sortBy(fn ($e) => $e->student?->last_name)
            ->values();
    }

    /**
     * Enter a candidate.
     *
     * @param  array<int, int>  $gceSubjectIds
     *
     * @throws RuntimeException when the entry would be rejected by the Board
     */
    public function register(
        GceRegistrationSession $session,
        Student $student,
        array $gceSubjectIds,
        ?StudentEnrollment $enrollment = null
    ): GceCandidate {
        $this->assertOpen($session);

        if ($session->candidates()->where('student_id', $student->id)->exists()) {
            throw new RuntimeException(__(':name is already entered for this series.', ['name' => $student->full_name]));
        }

        $subjects = $this->validSubjects($session, $gceSubjectIds);

        return DB::transaction(function () use ($session, $student, $enrollment, $subjects) {
            $candidate = GceCandidate::create([
                'branch_id' => $session->branch_id,
                'gce_registration_session_id' => $session->id,
                'student_id' => $student->id,
                'student_enrollment_id' => $enrollment?->id,
                'candidate_number' => $this->nextCandidateNumber($session),
                'fee_amount' => $session->feeFor($subjects->count()),
                'amount_paid' => 0,
                'status' => 'draft',
                'registered_by' => auth()->id(),
            ]);

            $candidate->subjects()->sync($subjects->pluck('id'));

            return $candidate;
        });
    }

    /**
     * Change a candidate's subjects, re-pricing the entry.
     *
     * @param  array<int, int>  $gceSubjectIds
     */
    public function updateSubjects(GceCandidate $candidate, array $gceSubjectIds): GceCandidate
    {
        $session = $candidate->registrationSession;

        $this->assertOpen($session);

        if (in_array($candidate->status, ['confirmed', 'withdrawn'], true)) {
            throw new RuntimeException(__('A :status entry can no longer be changed.', [
                'status' => __(ucfirst($candidate->status)),
            ]));
        }

        $subjects = $this->validSubjects($session, $gceSubjectIds);

        return DB::transaction(function () use ($candidate, $session, $subjects) {
            $candidate->subjects()->sync($subjects->pluck('id'));

            // The fee follows the subject count, so dropping a subject after
            // paying leaves a credit rather than a silent overcharge.
            $candidate->update(['fee_amount' => $session->feeFor($subjects->count())]);

            return $candidate->refresh();
        });
    }

    /** Move a candidate along the entry workflow. */
    public function setStatus(GceCandidate $candidate, string $status): GceCandidate
    {
        if (! in_array($status, ['draft', 'submitted', 'confirmed', 'withdrawn'], true)) {
            throw new RuntimeException(__('Unknown entry status.'));
        }

        // Confirmation is the Board accepting the entry, so it should not happen
        // while the school still owes the entry fee.
        if ($status === 'confirmed' && ! $candidate->isPaid()) {
            throw new RuntimeException(__('The entry fee is not fully paid, so this entry cannot be confirmed.'));
        }

        $candidate->update(['status' => $status]);

        return $candidate->refresh();
    }

    /**
     * Candidate numbers run within a series, prefixed by the centre number the
     * Board assigned to the school.
     */
    public function nextCandidateNumber(GceRegistrationSession $session): string
    {
        $prefix = $session->centre_number ? $session->centre_number.'-' : '';

        // Counted across branches: the unique index is on (session, number), so
        // a branch-scoped MAX would hand out a number that already exists.
        $used = GceCandidate::withoutBranchScope()
            ->where('gce_registration_session_id', $session->id)
            ->pluck('candidate_number')
            ->filter()
            ->map(fn ($number) => (int) substr((string) $number, strrpos((string) $number, '-') !== false
                ? strrpos((string) $number, '-') + 1
                : 0));

        return $prefix.str_pad((string) (($used->max() ?? 0) + 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<int, int>  $ids
     * @return Collection<int, GceSubject>
     */
    private function validSubjects(GceRegistrationSession $session, array $ids): Collection
    {
        $subjects = GceSubject::active()
            ->forLevel($session->level)
            ->whereIn('id', array_unique($ids))
            ->get();

        if ($subjects->count() < $session->min_subjects) {
            throw new RuntimeException(__('At least :min subject(s) must be entered for :level.', [
                'min' => $session->min_subjects,
                'level' => $session->level_label,
            ]));
        }

        if ($subjects->count() > $session->max_subjects) {
            throw new RuntimeException(__('No more than :max subject(s) may be entered for :level.', [
                'max' => $session->max_subjects,
                'level' => $session->level_label,
            ]));
        }

        return $subjects;
    }

    private function assertOpen(GceRegistrationSession $session): void
    {
        if (! $session->isOpen()) {
            throw new RuntimeException(__('This series is :status, so entries cannot be changed.', [
                'status' => __(ucfirst($session->status)),
            ]));
        }
    }
}
