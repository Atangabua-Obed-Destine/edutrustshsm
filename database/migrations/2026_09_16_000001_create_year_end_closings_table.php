<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The record of closing a fiscal year.
 *
 * Closing was a single button: it posted the closing entry and that was all
 * anyone could later learn about it. The reference system keeps a closing
 * record with a checklist, who did what, and why it was reversed. This follows
 * that: one row per attempt at closing a year, so a year closed, reopened and
 * closed again keeps both histories.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('year_end_closings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
            $table->foreignId('fiscal_year_id')->constrained('fiscal_years')->cascadeOnDelete();
            $table->enum('status', ['in_progress', 'closed', 'reversed'])->default('in_progress');

            // The confirmations a person has to make, keyed by item, each with
            // who confirmed it and when.
            $table->json('confirmations')->nullable();

            // The result as it stood when the year was closed.
            $table->decimal('total_revenue', 15, 2)->nullable();
            $table->decimal('total_expenses', 15, 2)->nullable();
            $table->decimal('net_result', 15, 2)->nullable();
            $table->foreignId('closing_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();

            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->string('reversal_reason', 500)->nullable();

            $table->timestamps();

            $table->index(['fiscal_year_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('year_end_closings');
    }
};
