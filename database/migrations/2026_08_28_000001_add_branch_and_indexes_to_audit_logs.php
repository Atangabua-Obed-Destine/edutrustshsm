<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The audit trail was a single global table with no tenant column, so branch
     * owners could not be shown their own activity. It also had no index on the
     * columns the log is actually filtered and sorted by.
     */
    public function up(): void
    {
        $mainBranchId = DB::table('branches')->orderBy('id')->value('id');

        if (! Schema::hasColumn('audit_logs', 'branch_id')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->foreignId('branch_id')->nullable()->after('id')
                    ->constrained('branches')->cascadeOnDelete();
            });

            DB::table('audit_logs')->whereNull('branch_id')->update(['branch_id' => $mainBranchId]);
        }

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['branch_id', 'created_at'], 'audit_logs_branch_created_index');
            $table->index('user_id', 'audit_logs_user_index');
            $table->index('action', 'audit_logs_action_index');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_logs_branch_created_index');
            $table->dropIndex('audit_logs_user_index');
            $table->dropIndex('audit_logs_action_index');
            $table->dropForeign(['branch_id']);
            $table->dropColumn('branch_id');
        });
    }
};
