<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

        DB::statement("ALTER TABLE sequences MODIFY COLUMN status ENUM('draft', 'active', 'completed', 'published') NOT NULL DEFAULT 'draft'");
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

        DB::statement("ALTER TABLE sequences MODIFY COLUMN status ENUM('draft', 'published') NOT NULL DEFAULT 'draft'");
    }
};
