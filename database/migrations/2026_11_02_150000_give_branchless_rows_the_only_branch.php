<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rows created outside a login — by a migration, a seeder, an import — were
 * saved with no branch, and every screen filters by branch, so they vanished:
 * account 419 "Student Advances / Credits" among them, which every
 * overpayment posts to. In a one-branch school they belong to that branch.
 *
 * Runs on any installation; does nothing where there is more than one active
 * branch, because there the choice is not this migration's to make.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('branches')) {
            return;
        }

        $active = DB::table('branches')->where('is_active', true)->limit(2)->pluck('id');
        if ($active->count() !== 1) {
            return;
        }
        $branch = $active->first();

        foreach ($this->tablesWithBranch() as $table) {
            if (in_array($table, ['audit_logs', 'branch_user', 'branches'], true)) {
                continue;
            }
            DB::table($table)->whereNull('branch_id')->update(['branch_id' => $branch]);
        }
    }

    public function down(): void
    {
        // Nothing to undo: the rows were invisible without a branch.
    }

    /** @return array<int, string> */
    private function tablesWithBranch(): array
    {
        return collect(Schema::getTables())
            ->pluck('name')
            ->filter(fn ($t) => Schema::hasColumn($t, 'branch_id'))
            ->values()
            ->all();
    }
};
