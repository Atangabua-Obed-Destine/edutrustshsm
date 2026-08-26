<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_account_types', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('payment_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('account_number')->nullable();
            $table->foreignId('account_type_id')->constrained('payment_account_types')->restrictOnDelete();
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->decimal('current_balance', 15, 2)->default(0);
            $table->text('description')->nullable();
            $table->boolean('status')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('payment_account_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_account_id')->constrained('payment_accounts')->cascadeOnDelete();
            $table->enum('transaction_type', ['debit', 'credit']); // credit = money in, debit = money out
            $table->decimal('amount', 15, 2);
            $table->date('transaction_date');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('reference_type')->nullable(); // income/expense/fee_payment/transfer/deposit/withdrawal
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable();
            $table->decimal('balance_after', 15, 2); // running balance snapshot after this row
            $table->string('attach')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['reference_type', 'reference_id'], 'pat_reference_index');
            $table->index(['payment_account_id', 'transaction_date'], 'pat_account_date_index');
        });

        Schema::create('payment_account_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_account_id')->constrained('payment_accounts')->restrictOnDelete();
            $table->foreignId('to_account_id')->constrained('payment_accounts')->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('transfer_date');
            $table->text('note')->nullable();
            $table->string('attach')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_account_transfers');
        Schema::dropIfExists('payment_account_transactions');
        Schema::dropIfExists('payment_accounts');
        Schema::dropIfExists('payment_account_types');
    }
};
