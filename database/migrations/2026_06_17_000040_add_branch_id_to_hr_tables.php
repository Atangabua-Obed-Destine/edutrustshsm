<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** HR / payroll tables that become branch-owned. */
    private array $tables = [
        'payrolls', 'payroll_details', 'staff_bank_accounts', 'staff_documents',
        'staff_tax_exemptions', 'tax_settings', 'tax_groups', 'allowance_types',
        'deduction_types', 'work_shift_types',
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
