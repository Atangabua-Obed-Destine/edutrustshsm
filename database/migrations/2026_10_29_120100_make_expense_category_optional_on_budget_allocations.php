<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An allocation on the Income & Expenditure sheet is against a budget line,
 * not an expense category — an income line has no expense category at all.
 * The column stays for departmental budgets; it just stops being required.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_allocations', function (Blueprint $table) {
            $table->unsignedBigInteger('expense_category_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('budget_allocations', function (Blueprint $table) {
            $table->unsignedBigInteger('expense_category_id')->nullable(false)->change();
        });
    }
};
