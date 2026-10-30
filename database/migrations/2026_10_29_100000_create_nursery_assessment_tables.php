<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nursery pupils are not marked out of twenty.
 *
 * Until now a four-year-old in Nursery 1 went through the same machinery as a
 * Form 5 candidate: marks per subject per sequence, a weighted average, a rank
 * in class, a PASS / FAIL bar. The Cameroon preschool curriculum assesses the
 * child against competences — "counts objects up to ten", "shares and takes
 * turns" — and records whether each is acquired, in progress, or not yet.
 *
 * The catalogue (learning areas and their competences) is the school's own,
 * seeded from the standard list but editable. A rating is one row per pupil,
 * term and competence. Remarks, attendance and publication reuse the existing
 * term_results row so that publishing, the parent portal and the report-card
 * list keep working without a second set of plumbing; for a nursery pupil that
 * row simply carries no average and no rank.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nursery_learning_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('name', 120);
            $table->unsignedTinyInteger('display_order')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('nursery_competences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('nursery_learning_area_id')->constrained('nursery_learning_areas')->cascadeOnDelete();
            // Null means every nursery class; set it to keep a competence to
            // one class, such as "writes own first name" for Nursery 2 only.
            $table->foreignId('form_id')->nullable()->constrained('forms')->nullOnDelete();
            $table->string('description', 255);
            $table->unsignedTinyInteger('display_order')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('nursery_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('student_enrollment_id')->constrained('student_enrollments')->cascadeOnDelete();
            $table->foreignId('term_id')->constrained('terms')->cascadeOnDelete();
            $table->foreignId('nursery_competence_id')->constrained('nursery_competences')->cascadeOnDelete();
            $table->string('rating', 12); // acquired | developing | not_yet
            $table->foreignId('assessed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['student_enrollment_id', 'term_id', 'nursery_competence_id'], 'nursery_assessment_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nursery_assessments');
        Schema::dropIfExists('nursery_competences');
        Schema::dropIfExists('nursery_learning_areas');
    }
};
