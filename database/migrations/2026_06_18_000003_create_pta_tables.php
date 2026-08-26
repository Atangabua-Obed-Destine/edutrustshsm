<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Announcements broadcast to parents (all / by form / by class).
        Schema::create('pta_announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('title');
            $table->text('body');
            $table->enum('audience', ['all', 'form_specific', 'class_specific'])->default('all');
            $table->foreignId('target_form_id')->nullable()->constrained('forms')->nullOnDelete();
            $table->foreignId('target_class_section_id')->nullable()->constrained('class_sections')->nullOnDelete();
            $table->timestamp('published_at')->nullable(); // null = draft
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['branch_id', 'published_at'], 'pta_ann_branch_pub_index');
        });

        // PTA meetings (schedule + minutes).
        Schema::create('pta_meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('title');
            $table->text('agenda')->nullable();
            $table->string('venue')->nullable();
            $table->dateTime('meeting_date');
            $table->string('minutes_path')->nullable();
            $table->enum('status', ['scheduled', 'completed', 'cancelled'])->default('scheduled');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['branch_id', 'meeting_date'], 'pta_meet_branch_date_index');
        });

        // PTA levy definition per session (optionally per form).
        Schema::create('pta_levies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->foreignId('form_id')->nullable()->constrained('forms')->nullOnDelete(); // null = all forms
            $table->decimal('amount', 12, 2);
            $table->date('due_date')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'academic_session_id'], 'pta_levy_branch_session_index');
        });

        // PTA levy payments (verified by admin, separate from school fees).
        Schema::create('pta_levy_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('pta_levy_id')->constrained('pta_levies')->cascadeOnDelete();
            $table->foreignId('guardian_id')->nullable()->constrained('guardians')->nullOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->decimal('amount_paid', 12, 2);
            $table->date('payment_date');
            $table->string('payment_method')->nullable();
            $table->string('receipt_ref')->nullable();
            $table->string('proof_path')->nullable();
            $table->enum('status', ['pending', 'verified'])->default('pending');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'status'], 'pta_levypay_branch_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pta_levy_payments');
        Schema::dropIfExists('pta_levies');
        Schema::dropIfExists('pta_meetings');
        Schema::dropIfExists('pta_announcements');
    }
};
