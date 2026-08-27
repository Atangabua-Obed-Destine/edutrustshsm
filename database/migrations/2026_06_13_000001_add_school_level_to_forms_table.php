<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Introduces a two-tier class taxonomy:
     *   school_level: nursery_primary | secondary
     *     - secondary       -> level: first_cycle | second_cycle
     *     - nursery_primary  -> level: nursery | primary
     *
     * `level` is widened from an enum to a string so it can hold the new
     * nursery/primary values; validation is enforced at the application layer.
     */
    public function up(): void
    {
        // Widen `level` from enum to a plain string (preserves existing rows).
        // MySQL-only DDL; on sqlite the column is already plain text.
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE forms MODIFY level VARCHAR(20) NOT NULL');
        }

        Schema::table('forms', function (Blueprint $table) {
            $table->string('school_level', 20)->default('secondary')->after('level');
        });

        // Backfill: every existing form belongs to the secondary school level.
        DB::table('forms')->update(['school_level' => 'secondary']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->dropColumn('school_level');
        });

        // Restore the original enum constraint on `level`.
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE forms MODIFY level ENUM('first_cycle','second_cycle') NOT NULL");
        }
    }
};
