<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A payment of withheld payroll tax to the authority it was withheld for.
 *
 * Paying a payslip posts the employee and employer tax to a liability account,
 * and nothing ever cleared it: the balance grew every month with no screen
 * saying the school was holding money that belonged to the tax office and the
 * CNPS. Follows the reference system's tax_remittances table — one row is one
 * declaration: this authority, this salary month, this amount, paid on this
 * date from this account, under this receipt.
 *
 * No unique index on (authority, month): a voided remittance keeps its row as
 * the audit trail, and "unique among rows that are not voided" cannot be an
 * index. The one-payment-per-month rule lives in TaxRemittanceService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_remittances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();

            // The authority, as the liability account payroll credited.
            $table->foreignId('liability_account_id')->constrained('chart_of_accounts')->restrictOnDelete();

            // YYYY-MM, matching payrolls.salary_month: the month the pay related
            // to, not the month the tax was handed over (payment_date).
            $table->string('salary_month', 7);

            $table->decimal('amount', 15, 2);
            $table->date('payment_date');

            // The cash or bank ledger account it left (class 5).
            $table->foreignId('source_account_id')->constrained('chart_of_accounts')->restrictOnDelete();

            // The treasury account debited, when one was chosen.
            $table->foreignId('payment_account_id')->nullable()->constrained('payment_accounts')->nullOnDelete();

            $table->string('reference', 191)->nullable();
            $table->text('note')->nullable();

            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();

            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason', 500)->nullable();
            $table->foreignId('void_journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['liability_account_id', 'salary_month'], 'remit_account_month_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_remittances');
    }
};
