<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Templates for entries that repeat — rent, depreciation, standing charges.
     *
     * The template holds its own lines; generating one copies them into a real
     * journal entry and advances next_run_date. Generation is idempotent per
     * due date: a template will not produce two entries for the same run.
     */
    public function up(): void
    {
        Schema::create('recurring_journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
            $table->string('title', 150);
            $table->string('description')->nullable();
            $table->enum('frequency', ['weekly', 'monthly', 'quarterly', 'yearly'])->default('monthly');
            $table->date('start_date');
            $table->date('end_date')->nullable();      // null = runs indefinitely
            $table->date('next_run_date');
            $table->date('last_run_date')->nullable();
            $table->unsignedInteger('runs_generated')->default(0);
            $table->boolean('auto_post')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'next_run_date']);
        });

        Schema::create('recurring_journal_entry_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recurring_journal_entry_id')->constrained('recurring_journal_entries', 'id', 'rje_lines_parent_fk')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->unsignedSmallInteger('line_number');
            $table->decimal('debit', 12, 2)->default(0);
            $table->decimal('credit', 12, 2)->default(0);
            $table->string('description')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_journal_entry_lines');
        Schema::dropIfExists('recurring_journal_entries');
    }
};
