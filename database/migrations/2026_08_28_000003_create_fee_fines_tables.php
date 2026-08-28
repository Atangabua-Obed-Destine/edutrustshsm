<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Late-payment penalties.
     *
     * A fine is a BAND: "between 1 and 30 days late, charge 5%". Bands are
     * attached to fee categories, so tuition can carry a penalty while a PTA
     * levy does not.
     *
     * The accrued amount lives on student_fees.fine_amount and is RECOMPUTED
     * from the bands, never incremented — so re-running the accrual is safe and
     * removing a band undoes its effect.
     */
    public function up(): void
    {
        Schema::create('fee_fines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
            $table->string('title', 100);
            $table->unsignedSmallInteger('start_day');            // inclusive, days past due
            $table->unsignedSmallInteger('end_day')->nullable();   // inclusive; null = open-ended
            $table->enum('type', ['fixed', 'percentage'])->default('fixed');
            $table->decimal('amount', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'start_day']);
        });

        Schema::create('fee_category_fee_fine', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_fine_id')->constrained('fee_fines')->cascadeOnDelete();
            $table->foreignId('fee_category_id')->constrained('fee_categories')->cascadeOnDelete();
            $table->unique(['fee_fine_id', 'fee_category_id'], 'fee_fine_category_unique');
        });

        Schema::table('student_fees', function (Blueprint $table) {
            $table->decimal('fine_amount', 12, 2)->default(0)->after('waiver_amount');
        });
    }

    public function down(): void
    {
        Schema::table('student_fees', function (Blueprint $table) {
            $table->dropColumn('fine_amount');
        });
        Schema::dropIfExists('fee_category_fee_fine');
        Schema::dropIfExists('fee_fines');
    }
};
