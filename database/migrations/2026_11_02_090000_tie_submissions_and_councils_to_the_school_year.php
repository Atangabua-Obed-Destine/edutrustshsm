<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tie marks submissions and class councils to the school year.
 *
 * Class sections, terms and sequences are permanent: P3A, First Term and
 * Sequence 1 exist every year. A marks submission was unique on (section,
 * subject, sequence) and a council on (section, term), so the second year's
 * Maths Seq 1 for P3A found the first year's published row and refused to
 * open, and the second year's council for P3A First Term could not be held.
 *
 * Existing rows are given the year their marks (or their results) belong
 * to, falling back to the current year.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('marks_submissions', 'academic_session_id')) {
            Schema::table('marks_submissions', function (Blueprint $table) {
                $table->foreignId('academic_session_id')->nullable()->after('class_section_id')->constrained('academic_sessions')->nullOnDelete();
            });
        }

        $current = DB::table('academic_sessions')->where('is_current', true)->value('id')
            ?? DB::table('academic_sessions')->orderByDesc('start_date')->value('id');

        foreach (DB::table('marks_submissions')->whereNull('academic_session_id')->get(['id', 'class_section_id', 'subject_id', 'sequence_id']) as $row) {
            $session = DB::table('marks')
                ->join('student_enrollments', 'student_enrollments.id', '=', 'marks.student_enrollment_id')
                ->where('marks.subject_id', $row->subject_id)
                ->where('marks.sequence_id', $row->sequence_id)
                ->where('student_enrollments.class_section_id', $row->class_section_id)
                ->value('student_enrollments.academic_session_id');

            DB::table('marks_submissions')->where('id', $row->id)->update(['academic_session_id' => $session ?? $current]);
        }

        if (! $this->hasIndex('marks_submissions', 'marks_sub_year_unique')) {
            Schema::table('marks_submissions', function (Blueprint $table) {
                $table->unique(['class_section_id', 'subject_id', 'sequence_id', 'academic_session_id'], 'marks_sub_year_unique');
            });
        }
        if ($this->hasIndex('marks_submissions', 'marks_sub_unique')) {
            Schema::table('marks_submissions', function (Blueprint $table) {
                $table->dropUnique('marks_sub_unique');
            });
        }

        foreach (DB::table('class_councils')->whereNull('academic_session_id')->get(['id', 'class_section_id', 'term_id']) as $row) {
            $session = DB::table('term_results')
                ->join('student_enrollments', 'student_enrollments.id', '=', 'term_results.student_enrollment_id')
                ->where('term_results.term_id', $row->term_id)
                ->where('student_enrollments.class_section_id', $row->class_section_id)
                ->value('student_enrollments.academic_session_id');

            DB::table('class_councils')->where('id', $row->id)->update(['academic_session_id' => $session ?? $current]);
        }

        if (! $this->hasIndex('class_councils', 'council_class_term_year_unique')) {
            Schema::table('class_councils', function (Blueprint $table) {
                $table->unique(['class_section_id', 'term_id', 'academic_session_id'], 'council_class_term_year_unique');
            });
        }
        if ($this->hasIndex('class_councils', 'council_class_term_unique')) {
            Schema::table('class_councils', function (Blueprint $table) {
                $table->dropUnique('council_class_term_unique');
            });
        }
    }

    private function hasIndex(string $table, string $name): bool
    {
        return collect(Schema::getIndexes($table))->contains(fn ($index) => $index['name'] === $name);
    }

    public function down(): void
    {
        Schema::table('class_councils', function (Blueprint $table) {
            $table->dropUnique('council_class_term_year_unique');
            $table->unique(['class_section_id', 'term_id'], 'council_class_term_unique');
        });

        Schema::table('marks_submissions', function (Blueprint $table) {
            $table->dropUnique('marks_sub_year_unique');
            $table->unique(['class_section_id', 'subject_id', 'sequence_id'], 'marks_sub_unique');
            $table->dropConstrainedForeignId('academic_session_id');
        });
    }
};
