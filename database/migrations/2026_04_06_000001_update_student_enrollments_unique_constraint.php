<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // Drop old index if it exists
        $indexExists = DB::select("SHOW INDEX FROM student_enrollments WHERE Key_name = 'student_enrollments_student_id_academic_session_id_unique'");
        if (!empty($indexExists)) {
            DB::statement('ALTER TABLE student_enrollments DROP INDEX student_enrollments_student_id_academic_session_id_unique');
        }

        // Add new composite unique (student + session + term)
        DB::statement('ALTER TABLE student_enrollments ADD UNIQUE INDEX enroll_student_session_term_unique (student_id, academic_session_id, term_id)');

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::table('student_enrollments', function (Blueprint $table) {
            $table->dropUnique('enroll_student_session_term_unique');
            $table->unique(['student_id', 'academic_session_id']);
        });

        Schema::enableForeignKeyConstraints();
    }
};
