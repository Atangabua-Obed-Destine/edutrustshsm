<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Money a student has paid beyond everything they owe.
     *
     * Previously the remainder of an over-payment was simply dropped: the
     * Payment recorded the full amount, the allocations summed to less, and the
     * difference existed nowhere. It is not revenue — it is money held on the
     * student's behalf — so it becomes a credit and a liability in the ledger
     * until it is applied to a future fee.
     */
    public function up(): void
    {
        Schema::create('student_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_enrollment_id')->constrained('student_enrollments')->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->decimal('used_amount', 12, 2)->default(0);
            $table->decimal('balance', 12, 2);
            $table->string('source', 30)->default('overpayment'); // overpayment | manual | refund_reversal
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['student_enrollment_id', 'balance']);
        });

        // Paying with a credit is not a cash movement, so it needs its own
        // payment method — otherwise the ledger would record cash twice.
        if (DB::getDriverName() === 'mysql' || DB::getDriverName() === 'mariadb') {
            DB::statement("ALTER TABLE payments MODIFY COLUMN payment_method ENUM('cash','bank_transfer','mtn_momo','orange_money','edutrustpay','student_credit') NOT NULL");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('student_credits');

        if (DB::getDriverName() === 'mysql' || DB::getDriverName() === 'mariadb') {
            DB::statement("ALTER TABLE payments MODIFY COLUMN payment_method ENUM('cash','bank_transfer','mtn_momo','orange_money','edutrustpay') NOT NULL");
        }
    }
};
