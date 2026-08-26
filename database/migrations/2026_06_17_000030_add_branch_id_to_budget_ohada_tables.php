<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Budget + OHADA accounting tables that become branch-owned. */
    private array $tables = [
        'budgets', 'budget_allocations', 'budget_revisions',
        'chart_of_accounts', 'journal_entries', 'journal_entry_lines',
        'fiscal_years', 'accounting_periods', 'default_account_mappings',
        'transaction_mappings',
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
