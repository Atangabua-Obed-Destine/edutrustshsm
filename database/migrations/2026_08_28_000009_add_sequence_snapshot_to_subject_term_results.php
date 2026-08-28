<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Two limitations of the fixed sequence_1..3_score columns:
     *
     *  - A term with four or more sequences silently lost the extra scores from
     *    the report card, even though they counted towards the average.
     *  - Nothing recorded WHICH weights produced a result, so changing a
     *    sequence weight or a subject coefficient rewrote history.
     *
     * `sequence_scores` holds every sequence's score, and `resolved_weights`
     * snapshots the weights actually used. The legacy columns stay populated so
     * existing report-card views keep working.
     */
    public function up(): void
    {
        Schema::table('subject_term_results', function (Blueprint $table) {
            $table->json('sequence_scores')->nullable()->after('sequence_3_score');
            $table->json('resolved_weights')->nullable()->after('sequence_scores');
        });
    }

    public function down(): void
    {
        Schema::table('subject_term_results', function (Blueprint $table) {
            $table->dropColumn(['sequence_scores', 'resolved_weights']);
        });
    }
};
