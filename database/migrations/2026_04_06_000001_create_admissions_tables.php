<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Applicants table (auth for public portal)
        Schema::create('applicants', function (Blueprint $table) {
            $table->id();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email', 150)->unique();
            $table->string('phone', 20)->nullable();
            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        // 2. Admission applications table
        Schema::create('admission_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained('applicants')->cascadeOnDelete();
            $table->string('application_number', 30)->unique();
            $table->foreignId('academic_session_id')->constrained('academic_sessions');

            // Class placement preferences
            $table->foreignId('form_id')->constrained('forms');
            $table->foreignId('stream_id')->nullable()->constrained('streams')->nullOnDelete();

            // Personal info (mirrors student fields)
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('other_names', 100)->nullable();
            $table->date('date_of_birth');
            $table->enum('gender', ['male', 'female']);
            $table->string('blood_group', 5)->nullable();
            $table->string('nationality', 100)->default('Cameroonian');
            $table->string('place_of_birth', 150)->nullable();
            $table->string('region_of_origin', 100)->nullable();
            $table->string('religion', 50)->nullable();

            // Contact
            $table->string('phone', 20)->nullable();
            $table->string('email', 100)->nullable();
            $table->text('home_address')->nullable();
            $table->string('town', 100)->nullable();

            // Previous school
            $table->string('previous_school', 200)->nullable();
            $table->string('previous_class', 100)->nullable();

            // Family / guardians
            $table->string('father_name', 150)->nullable();
            $table->string('father_phone', 20)->nullable();
            $table->string('father_email', 100)->nullable();
            $table->string('father_occupation', 100)->nullable();
            $table->string('father_address', 300)->nullable();
            $table->string('mother_name', 150)->nullable();
            $table->string('mother_phone', 20)->nullable();
            $table->string('mother_email', 100)->nullable();
            $table->string('mother_occupation', 100)->nullable();
            $table->string('mother_address', 300)->nullable();
            $table->string('guardian_name', 150)->nullable();
            $table->string('guardian_relationship', 50)->nullable();
            $table->string('guardian_phone', 20)->nullable();
            $table->string('guardian_email', 100)->nullable();
            $table->string('emergency_contact_name', 150);
            $table->string('emergency_contact_phone', 20);
            $table->string('emergency_contact_relationship', 50)->nullable();

            // Document uploads
            $table->string('photo')->nullable();
            $table->string('birth_certificate')->nullable();
            $table->string('primary_certificate')->nullable();
            $table->string('gce_ol_certificate')->nullable();
            $table->string('transfer_certificate')->nullable();
            $table->string('medical_certificate')->nullable();

            // Application status workflow
            $table->enum('status', ['pending', 'under_review', 'accepted', 'rejected', 'enrolled'])
                  ->default('pending');
            $table->text('admin_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            // Link to student record once enrolled
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();

            $table->timestamps();

            $table->index('status');
            $table->index(['applicant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_applications');
        Schema::dropIfExists('applicants');
    }
};
