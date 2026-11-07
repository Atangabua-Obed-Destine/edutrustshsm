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
        //
        // The new key goes on before the old one comes off. MySQL lets the
        // foreign key on student_id rest on the old key, and refuses to drop it
        // while nothing else can carry that foreign key. MariaDB does not mind.
        Schema::table('student_enrollments', function (Blueprint $table) {
            $table->unique(
                ['student_id', 'academic_session_id', 'term_id'],
                'enroll_student_session_term_unique'
            );
        });

        if ($this->hasIndex('student_enrollments_student_id_academic_session_id_unique')) {
            Schema::table('student_enrollments', function (Blueprint $table) {
                $table->dropUnique('student_enrollments_student_id_academic_session_id_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::table('student_enrollments', function (Blueprint $table) {
            $table->unique(['student_id', 'academic_session_id']);
        });

        Schema::table('student_enrollments', function (Blueprint $table) {
            $table->dropUnique('enroll_student_session_term_unique');
        });
    }

    private function hasIndex(string $name): bool
    {
        return collect(Schema::getIndexes('student_enrollments'))->contains(fn ($index) => $index['name'] === $name);
    }
};
