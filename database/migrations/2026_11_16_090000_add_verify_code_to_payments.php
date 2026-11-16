<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every receipt carries a check code. Anyone holding the paper (a parent,
     * an auditor, another school taking the pupil) can type it in, or follow
     * the printed link, and see whether the school recorded that receipt and
     * whether it still stands. Random rather than the receipt number, so the
     * receipts cannot be walked one after another.
     *
     * Older receipts get their code the first time they are printed again.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('verify_code', 12)->nullable()->unique()->after('receipt_number');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['verify_code']);
            $table->dropColumn('verify_code');
        });
    }
};
