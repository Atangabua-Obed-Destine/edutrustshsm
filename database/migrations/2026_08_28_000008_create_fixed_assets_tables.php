<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fixed asset register and its depreciation schedule.
     *
     * The schedule is generated up front, one row per period, so the whole life
     * of an asset is visible and each period can be posted (or not) on its own.
     * Posting a period writes DR depreciation expense / CR accumulated
     * depreciation through the normal journal-entry path.
     */
    public function up(): void
    {
        Schema::create('fixed_asset_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
            $table->string('name', 100);
            $table->unsignedSmallInteger('useful_life_years')->default(5);
            $table->enum('method', ['straight_line', 'declining'])->default('straight_line');
            $table->decimal('declining_rate', 5, 2)->nullable(); // % per year, declining only
            // Where this category's assets and their depreciation are booked.
            $table->foreignId('asset_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('depreciation_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('accumulated_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
            $table->foreignId('fixed_asset_category_id')->constrained('fixed_asset_categories')->restrictOnDelete();
            $table->string('code', 40)->unique();
            $table->string('name', 150);
            $table->string('description')->nullable();
            $table->date('acquisition_date');
            $table->decimal('cost', 14, 2);
            $table->decimal('salvage_value', 14, 2)->default(0);
            $table->unsignedSmallInteger('useful_life_years');
            $table->enum('method', ['straight_line', 'declining'])->default('straight_line');
            $table->decimal('declining_rate', 5, 2)->nullable();
            $table->string('location', 150)->nullable();
            $table->enum('status', ['active', 'disposed', 'written_off'])->default('active');
            $table->date('disposal_date')->nullable();
            $table->decimal('disposal_amount', 14, 2)->nullable();
            $table->string('disposal_note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'acquisition_date']);
        });

        Schema::create('depreciation_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixed_asset_id')->constrained('fixed_assets')->cascadeOnDelete();
            $table->unsignedSmallInteger('period_number');
            $table->date('period_date');                       // last day of the period
            $table->decimal('amount', 14, 2);
            $table->decimal('accumulated', 14, 2);             // after this period
            $table->decimal('book_value', 14, 2);              // after this period
            $table->boolean('is_posted')->default(false);
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->timestamps();

            $table->unique(['fixed_asset_id', 'period_number'], 'depreciation_asset_period_unique');
            $table->index(['is_posted', 'period_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('depreciation_schedules');
        Schema::dropIfExists('fixed_assets');
        Schema::dropIfExists('fixed_asset_categories');
    }
};
