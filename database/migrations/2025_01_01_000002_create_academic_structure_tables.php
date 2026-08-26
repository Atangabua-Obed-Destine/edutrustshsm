<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Academic Sessions (e.g., 2024/2025)
        Schema::create('academic_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 20)->unique(); // e.g., 2024/2025
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['upcoming', 'active', 'past'])->default('upcoming');
            $table->boolean('is_current')->default(false);
            $table->timestamps();

            $table->index('is_current');
        });

        // Terms within sessions
        Schema::create('terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->unsignedTinyInteger('term_number'); // 1, 2, 3
            $table->string('name', 50); // First Term, Second Term, Third Term
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_current')->default(false);
            $table->timestamps();

            $table->unique(['academic_session_id', 'term_number']);
            $table->index('is_current');
        });

        // Sequences (exams) within terms
        Schema::create('sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('term_id')->constrained('terms')->cascadeOnDelete();
            $table->unsignedTinyInteger('sequence_number');
            $table->string('name', 50); // Sequence 1, Sequence 2, Mock Exam
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('weight', 5, 2)->default(50.00); // % weight in term avg
            $table->date('marks_entry_deadline')->nullable();
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->timestamps();

            $table->unique(['term_id', 'sequence_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sequences');
        Schema::dropIfExists('terms');
        Schema::dropIfExists('academic_sessions');
    }
};
