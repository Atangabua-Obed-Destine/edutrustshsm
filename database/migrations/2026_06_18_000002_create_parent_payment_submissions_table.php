<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parent_payment_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('guardian_id')->constrained('guardians')->cascadeOnDelete();
            $table->foreignId('student_enrollment_id')->constrained('student_enrollments')->cascadeOnDelete();

            $table->decimal('amount', 12, 2);
            $table->enum('payment_method', ['bank_transfer', 'mtn_momo', 'orange_money', 'cash']);
            $table->string('bank_name')->nullable();
            $table->string('transaction_ref')->nullable();
            $table->date('payment_date');
            $table->string('receipt_path'); // uploaded proof file
            $table->text('notes')->nullable();

            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            // Link to the official Payment created on approval (for traceability).
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();

            $table->timestamps();

            $table->index(['branch_id', 'status'], 'pps_branch_status_index');
            $table->index('guardian_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parent_payment_submissions');
    }
};
