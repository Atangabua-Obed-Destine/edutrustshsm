<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The coded lines behind the Income & Expenditure sheet.
 *
 * These are deliberately NOT chart-of-accounts rows. The sheet the bursar
 * files numbers expenditure in the 400s and income in the 600s, whereas OHADA
 * reserves class 6 for charges and class 7 for revenue — forcing one scheme
 * into the other would corrupt the statutory chart. A budget line is a
 * management reporting dimension that a category points at, so the sheet can
 * be finer grained than the legal chart without distorting it.
 *
 * Money reaches a line through a category: default_account_mappings already
 * says where a category posts in the ledger, and now also says where it
 * appears on the sheet, so the two answers live on one row and cannot drift
 * apart. Expense and income rows can name a line directly too, so one
 * mis-filed transaction is corrected without changing the rule for its
 * whole category.
 *
 * Budgets gain an institutional flag and an opening balance, because the
 * sheet is bracketed by one and the closing balance of one year becomes the
 * opening balance of the next; allocations gain a budget_line_id so an income
 * line has a column to be budgeted in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('code', 20);
            $table->string('name');
            $table->string('name_fr')->nullable();
            // income | expenditure | capital. Capital sits below the closing
            // balance and is never part of expenditure.
            $table->string('section', 20)->index();
            // A heading (400 ADMINISTRATION) carries no figure of its own; it
            // totals the lines filed under it. Two levels deep, no more.
            $table->boolean('is_header')->default(false);
            $table->foreignId('parent_id')->nullable()->constrained('budget_lines')->nullOnDelete();
            // Which school level's tuition this line reports (nursery |
            // primary | ...). Only tuition is split this way; null elsewhere.
            $table->string('school_level', 30)->nullable()->index();
            // Lines sharing a profit-centre name are one trading activity —
            // the canteen's income line and its expenditure line pair up.
            $table->string('profit_centre')->nullable()->index();
            // Paid salaries have no category; the line that says so is fed
            // from payroll directly.
            $table->boolean('is_payroll')->default(false);
            // From the standard template, as opposed to added by this school.
            $table->boolean('is_standard')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('status')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('code');
        });

        Schema::table('default_account_mappings', function (Blueprint $table) {
            $table->foreignId('budget_line_id')->nullable()->after('category_id')->constrained('budget_lines')->nullOnDelete();
        });
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('budget_line_id')->nullable()->after('category_id')->constrained('budget_lines')->nullOnDelete();
        });
        Schema::table('incomes', function (Blueprint $table) {
            $table->foreignId('budget_line_id')->nullable()->after('category_id')->constrained('budget_lines')->nullOnDelete();
        });
        Schema::table('budget_allocations', function (Blueprint $table) {
            $table->foreignId('budget_line_id')->nullable()->after('expense_category_id')->constrained('budget_lines')->nullOnDelete();
        });
        Schema::table('budgets', function (Blueprint $table) {
            $table->boolean('is_institutional')->default(false)->after('type')->index();
            $table->decimal('opening_balance', 15, 2)->default(0)->after('total_amount');
        });

        // The reasoning behind a tuition figure, not just the figure: pupils
        // expected at a rate. Kept so next year starts from an assumption,
        // and a change to the fees shows as a difference to review.
        Schema::create('budget_line_forecasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained('budgets')->cascadeOnDelete();
            $table->foreignId('budget_line_id')->constrained('budget_lines')->cascadeOnDelete();
            $table->unsignedInteger('student_count')->default(0);
            $table->decimal('rate', 15, 2)->default(0);
            $table->decimal('computed_amount', 15, 2)->default(0);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['budget_id', 'budget_line_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_line_forecasts');
        Schema::table('budgets', function (Blueprint $table) {
            $table->dropColumn(['is_institutional', 'opening_balance']);
        });
        foreach (['budget_allocations', 'incomes', 'expenses', 'default_account_mappings'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('budget_line_id');
            });
        }
        Schema::dropIfExists('budget_lines');
    }
};
