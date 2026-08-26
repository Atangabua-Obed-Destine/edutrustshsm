<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Fees & finance tables that become branch-owned. */
    private array $tables = [
        'fee_categories', 'fee_structures', 'fee_breakdowns', 'fee_discounts',
        'student_fees', 'payments', 'payment_allocations', 'payment_plans',
        'payment_plan_installments', 'incomes', 'income_categories', 'expenses',
        'expense_categories', 'payment_accounts', 'payment_account_types',
        'payment_account_transactions', 'payment_account_transfers',
    ];

    public function up(): void
    {
        $mainBranchId = DB::table('branches')->orderBy('id')->value('id');

        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'branch_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) {
                $t->foreignId('branch_id')->nullable()->after('id')
                    ->constrained('branches')->cascadeOnDelete();
            });

            DB::table($table)->whereNull('branch_id')->update(['branch_id' => $mainBranchId]);
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'branch_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropForeign(['branch_id']);
                    $t->dropColumn('branch_id');
                });
            }
        }
    }
};
