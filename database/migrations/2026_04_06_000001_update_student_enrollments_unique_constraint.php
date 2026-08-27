<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A student now has one enrollment row per TERM, not per session, so the
        // unique key gains term_id. Written portably so the test suite (sqlite)
        // can run the same migration set as production (mysql).
        Schema::disableForeignKeyConstraints();

        Schema::table('student_enrollments', function (Blueprint $table) {
            try {
                $table->dropUnique('student_enrollments_student_id_academic_session_id_unique');
            } catch (\Throwable $e) {
                // Index already absent — nothing to drop.
            }
        });

        Schema::table('student_enrollments', function (Blueprint $table) {
            $table->unique(
                ['student_id', 'academic_session_id', 'term_id'],
                'enroll_student_session_term_unique'
            );
        });

        Schema::enableForeignKeyConstraints();
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
