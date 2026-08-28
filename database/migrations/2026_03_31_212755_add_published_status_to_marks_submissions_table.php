<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // MySQL-only DDL; on sqlite (test suite) the column is plain text.
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement("ALTER TABLE marks_submissions MODIFY COLUMN status ENUM('draft','submitted','approved','published','returned') NOT NULL DEFAULT 'draft'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // MySQL-only DDL; on sqlite (test suite) the column is plain text.
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement("ALTER TABLE marks_submissions MODIFY COLUMN status ENUM('draft','submitted','approved','returned') NOT NULL DEFAULT 'draft'");
    }
};
