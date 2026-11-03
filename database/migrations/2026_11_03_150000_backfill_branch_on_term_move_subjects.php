<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Moving a class into the next term copied each pupil's subjects without a
 * branch. Such rows are invisible to branch-scoped queries: the marks grid
 * then tried to add the subjects again and failed on the unique key, so no
 * marks could be entered after the first term. Give them their enrolment's.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('student_subjects')
            ->join('student_enrollments', 'student_enrollments.id', '=', 'student_subjects.student_enrollment_id')
            ->whereNull('student_subjects.branch_id')
            ->whereNotNull('student_enrollments.branch_id')
            ->get(['student_subjects.id', 'student_enrollments.branch_id']);

        foreach ($rows->groupBy('branch_id') as $branchId => $group) {
            DB::table('student_subjects')->whereIn('id', $group->pluck('id'))->update(['branch_id' => $branchId]);
        }
    }

    public function down(): void
    {
    }
};
