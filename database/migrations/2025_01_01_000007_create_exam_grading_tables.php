<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Grade Scale
        Schema::create('grade_scales', function (Blueprint $table) {
            $table->id();
            $table->decimal('min_mark', 4, 1);
            $table->decimal('max_mark', 4, 1);
            $table->string('grade', 5); // A+, A, A-, B+, B, B-, C+, C, D+, D, F
            $table->string('description', 50); // Exceptional, Excellent, Very Good, etc.
            $table->unsignedTinyInteger('display_order');
            $table->timestamps();
        });

        // Student Marks (per sequence per subject)
        Schema::create('marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_enrollment_id')->constrained('student_enrollments')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('sequence_id')->constrained('sequences')->cascadeOnDelete();
            $table->decimal('score', 4, 1)->nullable(); // 0-20 with 1 decimal
            $table->string('grade', 5)->nullable();
            $table->boolean('is_absent')->default(false);
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['draft', 'submitted', 'approved', 'published', 'returned'])->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('admin_comment')->nullable();
            $table->timestamps();

            $table->unique(['student_enrollment_id', 'subject_id', 'sequence_id'], 'marks_unique');
        });

        // Term Results (calculated averages per student per term)
        Schema::create('term_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_enrollment_id')->constrained('student_enrollments')->cascadeOnDelete();
            $table->foreignId('term_id')->constrained('terms')->cascadeOnDelete();
            $table->decimal('total_weighted_score', 8, 2)->nullable();
            $table->decimal('total_coefficient', 5, 1)->nullable();
            $table->decimal('term_average', 5, 2)->nullable();
            $table->string('overall_grade', 5)->nullable();
            $table->unsignedSmallInteger('class_rank')->nullable();
            $table->unsignedSmallInteger('total_students')->nullable();
            $table->decimal('class_average', 5, 2)->nullable();
            $table->decimal('highest_average', 5, 2)->nullable();
            $table->decimal('lowest_average', 5, 2)->nullable();
            $table->unsignedSmallInteger('days_present')->nullable();
            $table->unsignedSmallInteger('days_absent')->nullable();
            $table->unsignedSmallInteger('total_school_days')->nullable();
            $table->text('class_teacher_remark')->nullable();
            $table->text('principal_remark')->nullable();
            $table->enum('conduct', ['excellent', 'very_good', 'good', 'fair', 'poor'])->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            $table->unique(['student_enrollment_id', 'term_id']);
        });

        // Subject results per term (detailed breakdown for report card)
        Schema::create('subject_term_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('term_result_id')->constrained('term_results')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->decimal('coefficient', 3, 1)->default(1.0);
            $table->decimal('sequence_1_score', 4, 1)->nullable();
            $table->decimal('sequence_2_score', 4, 1)->nullable();
            $table->decimal('sequence_3_score', 4, 1)->nullable();
            $table->decimal('term_average', 5, 2)->nullable();
            $table->decimal('weighted_score', 6, 2)->nullable();
            $table->string('grade', 5)->nullable();
            $table->unsignedSmallInteger('subject_rank')->nullable();
            $table->unsignedSmallInteger('subject_total_students')->nullable();
            $table->string('teacher_name')->nullable();
            $table->timestamps();

            $table->unique(['term_result_id', 'subject_id']);
        });

        // Marks approval batch tracking
        Schema::create('marks_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_section_id')->constrained('class_sections')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('sequence_id')->constrained('sequences')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['draft', 'submitted', 'approved', 'returned'])->default('draft');
            $table->unsignedSmallInteger('total_students')->default(0);
            $table->unsignedSmallInteger('marks_entered')->default(0);
            $table->decimal('class_average', 5, 2)->nullable();
            $table->decimal('highest_mark', 4, 1)->nullable();
            $table->decimal('lowest_mark', 4, 1)->nullable();
            $table->decimal('pass_rate', 5, 2)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('admin_comment')->nullable();
            $table->timestamps();

            $table->unique(['class_section_id', 'subject_id', 'sequence_id'], 'marks_sub_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marks_submissions');
        Schema::dropIfExists('subject_term_results');
        Schema::dropIfExists('term_results');
        Schema::dropIfExists('marks');
        Schema::dropIfExists('grade_scales');
    }
};
