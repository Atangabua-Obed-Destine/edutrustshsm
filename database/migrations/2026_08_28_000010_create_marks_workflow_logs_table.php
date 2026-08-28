<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every state change a mark submission goes through.
     *
     * marks_submissions keeps only the LATEST approved_by/approved_at, so the
     * history of who submitted, who returned it and why was lost. Results
     * depend on this workflow now, which makes the trail worth keeping.
     */
    public function up(): void
    {
        Schema::create('marks_workflow_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marks_submission_id')->constrained('marks_submissions')->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['marks_submission_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marks_workflow_logs');
    }
};
