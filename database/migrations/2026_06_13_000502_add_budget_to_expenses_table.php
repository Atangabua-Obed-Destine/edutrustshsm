<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Links expenses to the budgeting layer so they can draw down an
     * allocation/budget. approval_status defaults to 'approved' (no approval
     * workflow); non-rejected expenses count toward budget spend.
     */
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('budget_id')->nullable()->after('payment_account_id')
                ->constrained('budgets')->nullOnDelete();
            $table->foreignId('budget_allocation_id')->nullable()->after('budget_id')
                ->constrained('budget_allocations')->nullOnDelete();
            $table->enum('approval_status', ['pending', 'approved', 'rejected'])->default('approved')->after('budget_allocation_id');
            $table->foreignId('approved_by')->nullable()->after('approval_status')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['budget_id']);
            $table->dropForeign(['budget_allocation_id']);
            $table->dropForeign(['approved_by']);
            $table->dropColumn(['budget_id', 'budget_allocation_id', 'approval_status', 'approved_by', 'approved_at']);
        });
    }
};
