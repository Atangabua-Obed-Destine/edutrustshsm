<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Chart of Accounts (Plan Comptable) ──
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('account_code')->unique();
            $table->string('account_name');
            $table->string('account_name_fr')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('chart_of_accounts')->restrictOnDelete();
            $table->unsignedTinyInteger('class_number'); // OHADA 1-8
            $table->enum('account_type', ['asset', 'liability', 'equity', 'revenue', 'expense', 'other']);
            $table->enum('account_category', ['detail', 'heading', 'total', 'subtotal'])->default('detail');
            $table->enum('normal_balance', ['debit', 'credit']);
            $table->decimal('opening_balance', 18, 2)->default(0);
            $table->decimal('current_balance', 18, 2)->default(0); // cached/advisory only
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->unsignedInteger('display_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index('class_number');
        });

        // ── Fiscal Years (Exercices) ──
        Schema::create('fiscal_years', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g. "2025/2026"
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(false);
            $table->boolean('is_closed')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // ── Accounting Periods (monthly) ──
        Schema::create('accounting_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscal_year_id')->constrained('fiscal_years')->cascadeOnDelete();
            $table->unsignedTinyInteger('period_number'); // 1-12
            $table->string('name'); // "January 2026"
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_closed')->default(false);
            $table->timestamps();
        });

        // ── Journal Entries (Écritures) ──
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('entry_number')->unique();
            $table->date('entry_date');
            $table->foreignId('fiscal_year_id')->nullable()->constrained('fiscal_years')->nullOnDelete();
            $table->foreignId('accounting_period_id')->nullable()->constrained('accounting_periods')->nullOnDelete();
            $table->enum('journal_type', ['general', 'sales', 'purchase', 'cash', 'bank', 'adjustment', 'opening', 'closing'])->default('general');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('description')->nullable();
            $table->decimal('total_debit', 18, 2)->default(0);
            $table->decimal('total_credit', 18, 2)->default(0);
            $table->boolean('is_posted')->default(false);
            $table->boolean('is_system_generated')->default(false);
            $table->boolean('is_reversed')->default(false);
            $table->foreignId('reversed_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['reference_type', 'reference_id'], 'je_reference_index');
        });

        Schema::create('journal_entry_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->unsignedInteger('line_number')->default(1);
            $table->decimal('debit', 18, 2)->default(0);
            $table->decimal('credit', 18, 2)->default(0);
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index('account_id');
        });

        // ── Auto-mapping ──
        Schema::create('default_account_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('mapping_type'); // fee_category / income_category / expense_category / payroll
            $table->unsignedBigInteger('category_id')->nullable();
            $table->foreignId('debit_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('credit_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->string('status')->default('active'); // string per guide gotcha #1
            $table->timestamps();

            $table->unique(['mapping_type', 'category_id'], 'dam_type_category_unique');
        });

        Schema::create('transaction_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_type'); // income / expense / fee_payment / payroll
            $table->unsignedBigInteger('transaction_id');
            $table->foreignId('debit_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('credit_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->decimal('amount', 18, 2)->default(0);
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->string('status')->default('active'); // active / reversed (string per gotcha #1)
            $table->timestamps();

            $table->unique(['transaction_type', 'transaction_id'], 'tm_type_id_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_mappings');
        Schema::dropIfExists('default_account_mappings');
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('accounting_periods');
        Schema::dropIfExists('fiscal_years');
        Schema::dropIfExists('chart_of_accounts');
    }
};
