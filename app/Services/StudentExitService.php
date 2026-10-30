<?php

namespace App\Services;

use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentFee;
use Illuminate\Support\Facades\DB;

/**
 * A pupil leaving the school.
 *
 * Until now there was no way to leave. `students.status` offered graduated,
 * withdrawn and expelled, but only the edit form ever touched it and nothing
 * wrote a date or a reason — so a pupil who left either stayed on the roll
 * forever or changed status with no record of why.
 *
 * The important half is not the student row. Class lists, fee reports and
 * attendance percentages all count `student_enrollments.status = 'active'`,
 * so an exit has to close the enrolment too. That is what actually takes a
 * departed pupil out of this term's numbers.
 */
class StudentExitService
{
    public const GRADUATED = 'graduated';
    public const TRANSFERRED = 'transferred';
    public const WITHDRAWN = 'withdrawn';
    public const EXPELLED = 'expelled';

    /**
     * How each kind of exit closes the enrolment.
     *
     * The enrolment vocabulary already existed and already meant the right
     * things; nothing had ever used it.
     */
    private const ENROLMENT_STATUS = [
        self::GRADUATED => 'completed',
        self::TRANSFERRED => 'transferred',
        self::WITHDRAWN => 'withdrawn',
        self::EXPELLED => 'withdrawn',
    ];

    /** @return array<string, string> type => label */
    public static function types(): array
    {
        return [
            self::GRADUATED => __('Graduated'),
            self::TRANSFERRED => __('Transferred to another school'),
            self::WITHDRAWN => __('Withdrawn'),
            self::EXPELLED => __('Expelled'),
        ];
    }

    /**
     * Record that a pupil has left.
     *
     * Deliberately does not refuse over unpaid fees. A school may not hold a
     * child's record hostage to a debt, and in practice the leaving and the
     * debt are settled by different people on different days — the balance is
     * surfaced to whoever records the exit instead.
     */
    public function record(Student $student, string $type, string $date, ?string $reason = null, ?string $destination = null): Student
    {
        return DB::transaction(function () use ($student, $type, $date, $reason, $destination) {
            $student->update([
                'status' => $type === self::TRANSFERRED ? self::WITHDRAWN : $type,
                'withdrawal_date' => $date,
                'withdrawal_reason' => $reason,
                'destination_school' => $type === self::TRANSFERRED ? $destination : null,
                'exit_recorded_by' => auth()->id(),
            ]);

            // The part that takes them off the lists.
            StudentEnrollment::where('student_id', $student->id)
                ->where('status', 'active')
                ->update(['status' => self::ENROLMENT_STATUS[$type] ?? 'withdrawn']);

            return $student->fresh();
        });
    }

    /**
     * Bring a pupil back.
     *
     * Re-admission reopens nothing by itself: the pupil is active again and
     * is enrolled afresh through the normal screens, because the class and
     * the year they return into are decisions, not a reversal.
     */
    public function readmit(Student $student): Student
    {
        $student->update([
            'status' => 'active',
            'withdrawal_date' => null,
            'withdrawal_reason' => null,
            'destination_school' => null,
            'exit_recorded_by' => null,
        ]);

        return $student->fresh();
    }

    /** Whether this pupil has left, by any route. */
    public function hasLeft(Student $student): bool
    {
        return in_array($student->status, [self::GRADUATED, self::WITHDRAWN, self::EXPELLED], true);
    }

    /**
     * What the pupil still owes, for the person recording the exit to see.
     *
     * Not a blocker — a fact worth knowing before the family walks out.
     */
    public function outstandingBalance(Student $student): float
    {
        return (float) StudentFee::whereHas('enrollment', fn ($q) => $q->where('student_id', $student->id))
            ->selectRaw('COALESCE(SUM(net_amount - paid_amount), 0) as balance')
            ->value('balance');
    }
}
