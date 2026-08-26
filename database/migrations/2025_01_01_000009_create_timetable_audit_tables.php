<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Timetable periods configuration
        Schema::create('timetable_slots', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('period_number');
            $table->time('start_time');
            $table->time('end_time');
            $table->enum('type', ['teaching', 'break', 'lunch'])->default('teaching');
            $table->string('label', 50)->nullable(); // Break, Lunch, Period 1
            $table->timestamps();

            $table->unique('period_number');
        });

        // Timetable entries
        Schema::create('timetable_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->foreignId('class_section_id')->constrained('class_sections')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->enum('day_of_week', ['monday', 'tuesday', 'wednesday', 'thursday', 'friday']);
            $table->foreignId('timetable_slot_id')->constrained('timetable_slots')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['class_section_id', 'day_of_week', 'timetable_slot_id'], 'tt_class_day_slot');
            $table->unique(['teacher_id', 'day_of_week', 'timetable_slot_id'], 'tt_teacher_day_slot');
        });

        // Audit logs
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 50); // create, update, delete, login, logout
            $table->string('model_type')->nullable();
            $table->unsignedBigInteger('model_id')->nullable();
            $table->text('old_values')->nullable();
            $table->text('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['model_type', 'model_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('timetable_entries');
        Schema::dropIfExists('timetable_slots');
    }
};
