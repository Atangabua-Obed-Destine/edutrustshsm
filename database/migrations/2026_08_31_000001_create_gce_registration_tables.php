<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GCE Ordinary and Advanced Level registration.
 *
 * Every Cameroonian secondary school files these entries with the GCE Board
 * each year, and the system had no notion of them at all — the whole exercise
 * was run on a spreadsheet alongside a system that already holds the students,
 * the classes and the subjects it needs.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The board's own subject catalogue. Its codes are what the Board reads,
        // and they do not match internal subject records, so this is a separate
        // list that internal subjects may optionally map onto.
        Schema::create('gce_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 150);
            $table->enum('level', ['o_level', 'a_level']);
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['branch_id', 'code', 'level'], 'gce_subject_code_unique');
        });

        // One exam series: "June 2026, Ordinary Level".
        Schema::create('gce_registration_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->string('name', 100);                 // "June 2026 O-Level"
            $table->enum('level', ['o_level', 'a_level']);
            $table->unsignedSmallInteger('exam_year');
            $table->string('centre_number', 20)->nullable();   // assigned by the Board
            $table->date('opens_on')->nullable();
            $table->date('closes_on')->nullable();

            // Subject-count rules vary by series, so they are set per session
            // rather than hardcoded to whatever this year's rules happen to be.
            $table->unsignedTinyInteger('min_subjects')->default(1);
            $table->unsignedTinyInteger('max_subjects')->default(9);

            $table->decimal('fee_per_subject', 12, 2)->default(0);
            $table->decimal('base_fee', 12, 2)->default(0);
            $table->enum('status', ['draft', 'open', 'closed', 'submitted'])->default('draft');
            $table->timestamps();
        });

        // Which classes this series is for, so "who has not registered yet" is a
        // question the system can answer.
        Schema::create('gce_session_form', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gce_registration_session_id')->constrained('gce_registration_sessions')->cascadeOnDelete();
            $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();

            $table->unique(['gce_registration_session_id', 'form_id'], 'gce_session_form_unique');
        });

        Schema::create('gce_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
            $table->foreignId('gce_registration_session_id')->constrained('gce_registration_sessions')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('student_enrollment_id')->nullable()->constrained('student_enrollments')->nullOnDelete();
            $table->string('candidate_number', 30)->nullable();
            $table->decimal('fee_amount', 12, 2)->default(0);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->enum('status', ['draft', 'submitted', 'confirmed', 'withdrawn'])->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // A student sits one series once.
            $table->unique(['gce_registration_session_id', 'student_id'], 'gce_candidate_unique');
            $table->unique(['gce_registration_session_id', 'candidate_number'], 'gce_candidate_number_unique');
        });

        Schema::create('gce_candidate_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gce_candidate_id')->constrained('gce_candidates')->cascadeOnDelete();
            $table->foreignId('gce_subject_id')->constrained('gce_subjects')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['gce_candidate_id', 'gce_subject_id'], 'gce_candidate_subject_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gce_candidate_subjects');
        Schema::dropIfExists('gce_candidates');
        Schema::dropIfExists('gce_session_form');
        Schema::dropIfExists('gce_registration_sessions');
        Schema::dropIfExists('gce_subjects');
    }
};
