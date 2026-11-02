<?php

namespace App\Observers;

use App\Models\StudentEnrollment;
use Illuminate\Support\Facades\Log;

class StudentEnrollmentObserver
{
    /**
     * Handle the StudentEnrollment "created" event.
     * Auto-assigns fees from configured fee structures.
     */
    public function created(StudentEnrollment $enrollment): void
    {
        if ($enrollment->status !== 'active') {
            return;
        }

        // Fees are billed for the year; a pupil gets a new enrolment row
        // each term. When this is a later term of the same year, the year's
        // fees and discounts move with the pupil. Otherwise the new row was
        // billed the year's tuition again, and last term's unpaid balance sat
        // on an enrolment the collection screen no longer shows. Payments
        // keep pointing at the enrolment they were taken on; their
        // allocations point at the fees, which is what moves.
        $earlier = StudentEnrollment::where('student_id', $enrollment->student_id)
            ->where('academic_session_id', $enrollment->academic_session_id)
            ->where('id', '!=', $enrollment->id)
            ->pluck('id');

        if ($earlier->isNotEmpty()) {
            \App\Models\StudentFee::whereIn('student_enrollment_id', $earlier)->update(['student_enrollment_id' => $enrollment->id]);
            \App\Models\FeeDiscount::whereIn('student_enrollment_id', $earlier)->update(['student_enrollment_id' => $enrollment->id]);
            // A family's advance moves too, or it sits on a past term where
            // nothing can apply it to this term's fees.
            \App\Models\StudentCredit::whereIn('student_enrollment_id', $earlier)->update(['student_enrollment_id' => $enrollment->id]);
        }

        try {
            $result = $enrollment->syncFees();

            if ($result['assigned'] > 0) {
                Log::info('Auto-assigned fees for enrollment', [
                    'enrollment_id' => $enrollment->id,
                    'student_id' => $enrollment->student_id,
                    'session_id' => $enrollment->academic_session_id,
                    'assigned' => $result['assigned'],
                    'skipped' => $result['skipped'],
                    'categories' => $result['categories'],
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to auto-assign fees for enrollment', [
                'enrollment_id' => $enrollment->id,
                'student_id' => $enrollment->student_id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
