<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Org structure ──
        Schema::create('designations', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->nullable();
            $table->text('description')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('work_shift_types', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->nullable();
            $table->text('description')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        // ── Staff financial / documents ──
        Schema::create('staff_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('bank_name');
            $table->string('account_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('bank_branch')->nullable();
            $table->string('ifsc_code')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('staff_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('file');
            $table->timestamps();
        });

        // ── Payroll config ──
        Schema::create('allowance_types', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('deduction_types', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        // ── Tax engine ──
        Schema::create('tax_groups', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('code')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_progressive')->default(true);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('tax_settings', function (Blueprint $table) {
            $table->id();
            $table->string('tax_title');
            $table->foreignId('tax_group_id')->nullable()->constrained('tax_groups')->cascadeOnDelete();
            $table->unsignedInteger('bracket_order')->default(0);
            $table->decimal('min_amount', 18, 2)->default(0);
            $table->decimal('max_amount', 18, 2)->default(0);
            $table->decimal('max_no_taxable_amount', 18, 2)->default(0);
            $table->unsignedTinyInteger('tax_type')->default(1); // 1=percentage, 2=fixed
            $table->decimal('percentage', 8, 4)->default(0);
            $table->decimal('fixed_amount', 18, 2)->default(0);
            $table->decimal('employer_percentage', 8, 4)->default(0);
            $table->decimal('employer_fixed_amount', 18, 2)->default(0);
            $table->string('paid_by')->default('employee'); // employee / employer / both
            $table->boolean('is_shared')->default(false);
            $table->boolean('is_dependent')->default(false);
            $table->string('depends_on_type')->nullable(); // tax_group / tax_setting
            $table->unsignedBigInteger('depends_on_id')->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('staff_tax_exemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('tax_setting_id')->constrained('tax_settings')->cascadeOnDelete();
            $table->decimal('custom_percentage', 8, 4)->nullable();
            $table->decimal('custom_fixed_amount', 18, 2)->nullable();
            $table->string('reason')->nullable();
            $table->date('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'tax_setting_id'], 'ste_user_tax_unique');
        });

        // ── Payroll run ──
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('basic_salary', 15, 2)->default(0);
            $table->unsignedTinyInteger('salary_type')->default(1);
            $table->decimal('total_earning', 15, 2)->default(0);
            $table->decimal('total_allowance', 15, 2)->default(0);
            $table->decimal('bonus', 15, 2)->default(0);
            $table->decimal('total_deduction', 15, 2)->default(0);
            $table->decimal('gross_salary', 15, 2)->default(0);
            $table->decimal('tax', 15, 2)->default(0);          // employee tax
            $table->decimal('employer_tax', 15, 2)->default(0);
            $table->decimal('net_salary', 15, 2)->default(0);
            $table->decimal('total_cost', 15, 2)->default(0);
            $table->string('salary_month'); // YYYY-MM
            $table->date('pay_date')->nullable();
            $table->string('payment_method')->nullable();
            $table->foreignId('payment_account_id')->nullable()->constrained('payment_accounts')->nullOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained('staff_bank_accounts')->nullOnDelete();
            $table->unsignedTinyInteger('status')->default(0); // 0=unpaid, 1=paid
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'salary_month'], 'payroll_user_month_unique');
        });

        Schema::create('payroll_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_id')->constrained('payrolls')->cascadeOnDelete();
            $table->string('title');
            $table->decimal('amount', 15, 2)->default(0);
            $table->unsignedTinyInteger('status')->default(1); // 0=deduction, 1=allowance
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_details');
        Schema::dropIfExists('payrolls');
        Schema::dropIfExists('staff_tax_exemptions');
        Schema::dropIfExists('tax_settings');
        Schema::dropIfExists('tax_groups');
        Schema::dropIfExists('deduction_types');
        Schema::dropIfExists('allowance_types');
        Schema::dropIfExists('staff_documents');
        Schema::dropIfExists('staff_bank_accounts');
        Schema::dropIfExists('work_shift_types');
        Schema::dropIfExists('designations');
    }
};
