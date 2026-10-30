<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Which level a subject is taught at.
 *
 * Subjects were one flat list, so a school working in Nursery / Primary was
 * offered Further Mathematics and Power Electronics, and a secondary school
 * was offered Sounds and Word Building.
 *
 * NULL means both levels, and it is the default on purpose: English,
 * Mathematics and French are taught at every level, and making a subject
 * choose one would mean keeping two records with two sets of marks for the
 * same subject.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->string('school_level', 20)->nullable()->after('code');
            $table->index(['branch_id', 'school_level'], 'subjects_branch_level_index');
        });

        $this->inferFromCurriculum();
    }

    /**
     * Read each subject's level off the classes it is actually taught in.
     *
     * The curriculum already says this: a subject mapped only to secondary
     * classes is a secondary subject. A subject taught at both levels, or
     * mapped to no class at all, is left as NULL — available everywhere —
     * because the evidence does not support narrowing it.
     */
    private function inferFromCurriculum(): void
    {
        $levels = DB::table('form_subject')
            ->join('forms', 'forms.id', '=', 'form_subject.form_id')
            ->whereNotNull('forms.school_level')
            ->select('form_subject.subject_id', 'forms.school_level')
            ->distinct()
            ->get()
            ->groupBy('subject_id');

        foreach ($levels as $subjectId => $rows) {
            $distinct = $rows->pluck('school_level')->unique();

            // Taught at both: it stays available to both.
            if ($distinct->count() !== 1) {
                continue;
            }

            DB::table('subjects')
                ->where('id', $subjectId)
                ->update(['school_level' => $distinct->first()]);
        }
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropIndex('subjects_branch_level_index');
            $table->dropColumn('school_level');
        });
    }
};
