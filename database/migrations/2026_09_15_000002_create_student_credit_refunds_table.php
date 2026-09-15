<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Money held on a student's account could only ever be spent on fees.
 *
 * A family leaving the school, or one that simply overpaid, had no way to get
 * the money back through the system. The reference system handles this as a
 * request → approve or reject → process lifecycle; this follows the same shape,
 * with one row per refund rather than flags on the credit, so a credit can be
 * refunded in more than one part and each part has its own ledger posting and
 * its own line in the payment account book.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_credits', function (Blueprint $table) {
            $table->decimal('refunded_amount', 12, 2)->default(0)->after('used_amount');
        });

        Schema::create('student_credit_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_credit_id')->constrained('student_credits')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('reason', 500);
            $table->enum('status', ['requested', 'approved', 'rejected', 'processed'])->default('requested');

            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at')->nullable();

            // Approval and rejection are the same decision point, recorded once.
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('rejection_reason', 500)->nullable();

            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->enum('method', ['cash', 'bank_transfer', 'cheque', 'mobile_money'])->nullable();
            $table->string('reference', 100)->nullable();
            $table->foreignId('payment_account_id')->nullable()->constrained('payment_accounts')->nullOnDelete();
            $table->string('note', 500)->nullable();

            $table->timestamps();

            $table->index(['student_credit_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_credit_refunds');

        Schema::table('student_credits', function (Blueprint $table) {
            $table->dropColumn('refunded_amount');
        });
    }
};
