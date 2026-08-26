<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guardians
        Schema::create('guardians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // portal account
            $table->string('father_name')->nullable();
            $table->string('father_phone')->nullable();
            $table->string('father_email')->nullable();
            $table->string('father_occupation')->nullable();
            $table->string('father_address')->nullable();
            $table->string('mother_name')->nullable();
            $table->string('mother_phone')->nullable();
            $table->string('mother_email')->nullable();
            $table->string('mother_occupation')->nullable();
            $table->string('mother_address')->nullable();
            $table->string('guardian_name')->nullable();
            $table->string('guardian_relationship')->nullable();
            $table->string('guardian_phone')->nullable();
            $table->string('guardian_email')->nullable();
            $table->string('emergency_contact_name');
            $table->string('emergency_contact_relationship')->nullable();
            $table->string('emergency_contact_phone');
            $table->timestamps();
        });

        // Students
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('student_id', 20)->unique(); // LCC/2025/001
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // portal account
            $table->string('first_name');
            $table->string('last_name');
            $table->string('other_names')->nullable();
            $table->date('date_of_birth');
            $table->enum('gender', ['male', 'female']);
            $table->string('nationality')->default('Cameroonian');
            $table->string('place_of_birth')->nullable();
            $table->string('religion')->nullable();
            $table->string('blood_group', 5)->nullable();
            $table->string('photo')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('home_address')->nullable();
            $table->foreignId('guardian_id')->nullable()->constrained('guardians')->nullOnDelete();
            $table->text('allergies')->nullable();
            $table->text('medical_conditions')->nullable();
            $table->text('special_needs')->nullable();
            $table->string('birth_certificate')->nullable(); // file path
            $table->string('previous_school')->nullable();
            $table->string('previous_class')->nullable();
            $table->string('transfer_certificate')->nullable(); // file path
            $table->enum('status', ['active', 'graduated', 'withdrawn', 'suspended', 'expelled'])->default('active');
            $table->date('admission_date');
            $table->date('withdrawal_date')->nullable();
            $table->text('withdrawal_reason')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        // Student enrollment per session (links student to class each year)
        Schema::create('student_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->foreignId('class_section_id')->constrained('class_sections')->cascadeOnDelete();
            $table->foreignId('stream_id')->nullable()->constrained('streams')->nullOnDelete();
            $table->enum('residence_type', ['day', 'boarding', 'half_boarding'])->default('day');
            $table->date('enrollment_date');
            $table->enum('status', ['active', 'completed', 'transferred', 'withdrawn', 'promoted', 'repeated'])->default('active');
            $table->decimal('final_average', 5, 2)->nullable();
            $table->unsignedSmallInteger('final_rank')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'academic_session_id']);
        });

        // Student-Subject enrollment (which electives each student takes)
        Schema::create('student_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_enrollment_id')->constrained('student_enrollments')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->decimal('coefficient', 3, 1)->default(1.0);
            $table->timestamps();

            $table->unique(['student_enrollment_id', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_subjects');
        Schema::dropIfExists('student_enrollments');
        Schema::dropIfExists('students');
        Schema::dropIfExists('guardians');
    }
};
