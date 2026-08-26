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
