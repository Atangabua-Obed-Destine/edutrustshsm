<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What is needed to record a pupil leaving.
 *
 * `status`, `withdrawal_date` and `withdrawal_reason` already existed on the
 * students table. The first was only ever set to 'active' and the other two
 * were written by nothing at all, so a pupil who left either stayed on the
 * roll forever or changed status with no date, no reason and nobody named
 * against it.
 *
 * Only two columns are new. The rest of the work is giving the existing ones
 * something that writes them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Where a transfer went. A leaving certificate names the school,
            // and "transferred" without it answers half the question.
            $table->string('destination_school', 200)->nullable()->after('withdrawal_reason');
            $table->foreignId('exit_recorded_by')->nullable()->after('destination_school')
                ->constrained('users')->nullOnDelete();

            $table->index(['branch_id', 'status'], 'students_branch_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex('students_branch_status_index');
            $table->dropConstrainedForeignId('exit_recorded_by');
            $table->dropColumn('destination_school');
        });
    }
};
