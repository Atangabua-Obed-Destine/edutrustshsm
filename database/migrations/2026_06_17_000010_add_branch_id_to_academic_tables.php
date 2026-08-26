<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Academic & people tables that become branch-owned. */
    private array $tables = [
        'academic_sessions', 'terms', 'sequences', 'forms', 'streams', 'subjects',
        'departments', 'designations', 'grade_scales', 'class_sections', 'rooms', 'batches',
        'students', 'student_enrollments', 'student_subjects', 'teacher_subject_assignments',
        'exam_schedules', 'marks', 'marks_submissions', 'term_results', 'subject_term_results',
        'attendances', 'timetable_slots', 'timetable_entries', 'admission_applications',
        'applicants', 'guardians',
    ];

    public function up(): void
    {
        $mainBranchId = DB::table('branches')->orderBy('id')->value('id');

        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'branch_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) {
                $t->foreignId('branch_id')->nullable()->after('id')
                    ->constrained('branches')->cascadeOnDelete();
            });

            // Backfill all existing rows to the Main Branch.
            DB::table($table)->whereNull('branch_id')->update(['branch_id' => $mainBranchId]);
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'branch_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropForeign(['branch_id']);
                    $t->dropColumn('branch_id');
                });
            }
        }
    }
};
