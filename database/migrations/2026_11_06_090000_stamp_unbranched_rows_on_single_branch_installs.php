<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A fresh install seeded its classes, subjects, streams, grade scale and
     * fee categories with model events switched off, so none of them got a
     * branch, and every screen (which shows one branch's rows) hid them: the
     * new school opened with no Form 1 to edit and no subjects to give it.
     *
     * In a one-branch school a row can only belong to that branch, so give it
     * that branch. Schools with several branches are left alone: there, which
     * branch a row belongs to is a decision, not a repair. The audit trail
     * keeps its system entries as they are.
     */
    public function up(): void
    {
        $branches = DB::table('branches')->where('is_active', true)->limit(2)->pluck('id');
        if ($branches->count() !== 1) {
            return;
        }
        $branchId = (int) $branches->first();

        foreach ($this->branchTables() as $table) {
            if ($table === 'audit_logs') {
                continue;
            }
            DB::table($table)->whereNull('branch_id')->update(['branch_id' => $branchId]);
        }

        // The installer's administrator was never attached to the branch.
        $now = now();
        foreach (DB::table('users')->whereNotIn('id', DB::table('branch_user')->select('user_id'))->pluck('id') as $userId) {
            DB::table('branch_user')->insert(['branch_id' => $branchId, 'user_id' => $userId, 'is_default' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        // Nothing to undo: rows without a branch were invisible.
    }

    /** @return array<int, string> */
    private function branchTables(): array
    {
        return collect(Schema::getTables())
            ->pluck('name')
            ->unique()
            // MySQL lists other databases' tables too; hasColumn looks in this one.
            ->filter(fn ($table) => $table !== 'branch_user' && Schema::hasColumn($table, 'branch_id'))
            ->values()
            ->all();
    }
};
