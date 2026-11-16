<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The year-end class council decides each pupil's next year: moves up,
 * repeats, or is asked to leave. Promotion follows that decision rather than
 * the average alone, as in the reference system, where the council's word
 * is what the school stands behind.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_council_entries', function (Blueprint $table) {
            $table->string('year_decision', 20)->nullable()->after('award');
        });
    }

    public function down(): void
    {
        Schema::table('class_council_entries', function (Blueprint $table) {
            $table->dropColumn('year_decision');
        });
    }
};
