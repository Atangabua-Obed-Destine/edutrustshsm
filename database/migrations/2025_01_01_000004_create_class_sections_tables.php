<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rooms (classrooms, labs, etc.)
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50); // Room 1, Lab 1
            $table->enum('type', ['classroom', 'lab', 'hall', 'office', 'other'])->default('classroom');
            $table->unsignedSmallInteger('capacity')->default(60);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Class Sections (Form 3A, Form 4B, etc.)
        Schema::create('class_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();
            $table->string('section', 5)->default('A'); // A, B, C, D
            $table->string('name', 100); // Form 3A, Form 4B
            $table->foreignId('class_teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->unsignedSmallInteger('max_students')->default(60);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['form_id', 'section'], 'cs_form_section_unique');
        });

        // Teacher-Subject-Class assignment (who teaches what where)
        Schema::create('teacher_subject_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // teacher
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('class_section_id')->constrained('class_sections')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['academic_session_id', 'subject_id', 'class_section_id'], 'teacher_subj_class_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_subject_assignments');
        Schema::dropIfExists('class_sections');
        Schema::dropIfExists('rooms');
    }
};
