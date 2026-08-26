<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fee Categories
        Schema::create('fee_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 20)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_mandatory')->default(true);
            $table->boolean('is_refundable')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Fee Structures (annual fee per form/residence)
        Schema::create('fee_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();
            $table->enum('residence_type', ['day', 'boarding', 'half_boarding']);
            $table->foreignId('fee_category_id')->constrained('fee_categories')->cascadeOnDelete();
            $table->decimal('amount', 12, 2); // annual amount in XAF
            $table->timestamps();

            $table->unique(['academic_session_id', 'form_id', 'residence_type', 'fee_category_id'], 'fee_struct_unique');
        });

        // Student Fee Assignments (actual fees assigned to individual student)
        Schema::create('student_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_enrollment_id')->constrained('student_enrollments')->cascadeOnDelete();
            $table->foreignId('fee_category_id')->constrained('fee_categories')->cascadeOnDelete();
            $table->decimal('original_amount', 12, 2);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('waiver_amount', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2); // original - discount - waiver
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('balance', 12, 2);
            $table->enum('status', ['unpaid', 'partial', 'paid', 'overpaid', 'waived'])->default('unpaid');
            $table->timestamps();

            $table->unique(['student_enrollment_id', 'fee_category_id']);
        });

        // Payments
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number', 30)->unique();
            $table->foreignId('student_enrollment_id')->constrained('student_enrollments')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->enum('payment_method', ['cash', 'bank_transfer', 'mtn_momo', 'orange_money', 'edutrustpay']);
            $table->date('payment_date');
            $table->string('payer_name')->nullable();
            $table->string('payer_phone')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('transaction_ref')->nullable();
            $table->string('proof_document')->nullable();
            $table->enum('verification_status', ['pending', 'verified', 'rejected'])->default('verified');
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Payment allocations (which fee each payment covers)
        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->foreignId('student_fee_id')->constrained('student_fees')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->timestamps();
        });

        // Discounts
        Schema::create('fee_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_enrollment_id')->constrained('student_enrollments')->cascadeOnDelete();
            $table->enum('discount_type', ['percentage', 'fixed']);
            $table->decimal('value', 12, 2); // percent or amount
            $table->enum('reason', ['scholarship', 'staff_child', 'sibling', 'financial_hardship', 'merit', 'other']);
            $table->text('description')->nullable();
            $table->enum('apply_to', ['all_fees', 'specific_category']);
            $table->foreignId('fee_category_id')->nullable()->constrained('fee_categories')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_discounts');
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('student_fees');
        Schema::dropIfExists('fee_structures');
        Schema::dropIfExists('fee_categories');
    }
};
