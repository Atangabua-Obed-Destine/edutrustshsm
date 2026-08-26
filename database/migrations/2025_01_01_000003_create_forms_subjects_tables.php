<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Forms (pre-loaded: Form 1-5, Lower Sixth, Upper Sixth)
        Schema::create('forms', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50); // Form 1, Form 2, ..., Lower Sixth, Upper Sixth
            $table->string('short_name', 20); // F1, F2, ..., LS, US
            $table->enum('level', ['first_cycle', 'second_cycle']);
            $table->boolean('has_streams')->default(false);
            $table->unsignedTinyInteger('display_order');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Streams (Science, Arts, Commercial)
        Schema::create('streams', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50); // Science, Arts, Commercial
            $table->string('code', 10)->unique(); // SCI, ART, COM
            $table->text('description')->nullable();
            $table->boolean('is_general')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Which streams apply to which forms
        Schema::create('form_stream', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();
            $table->foreignId('stream_id')->constrained('streams')->cascadeOnDelete();

            $table->unique(['form_id', 'stream_id']);
        });

        // Departments for organizing subjects
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique(); // Science, Arts, Languages, Commercial, Other
            $table->timestamps();
        });

        // Subjects
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 20)->unique();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Form-Subject assignments (which subjects in which form/stream)
        Schema::create('form_subject', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('stream_id')->nullable()->constrained('streams')->nullOnDelete();
            $table->decimal('coefficient', 3, 1)->default(1.0);
            $table->enum('type', ['core', 'elective'])->default('core');

            $table->unique(['form_id', 'subject_id', 'stream_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_subject');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('form_stream');
        Schema::dropIfExists('streams');
        Schema::dropIfExists('forms');
    }
};
