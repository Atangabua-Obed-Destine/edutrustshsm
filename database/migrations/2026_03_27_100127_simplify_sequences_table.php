<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Must drop FK first, then the unique index
        Schema::table('sequences', function (Blueprint $table) {
            $table->dropForeign(['term_id']);
        });

        Schema::table('sequences', function (Blueprint $table) {
            $table->dropUnique(['term_id', 'sequence_number']);
        });

        Schema::table('sequences', function (Blueprint $table) {
            $table->unsignedBigInteger('term_id')->nullable()->change();
            $table->unsignedTinyInteger('sequence_number')->nullable()->change();
            $table->date('start_date')->nullable()->change();
            $table->date('end_date')->nullable()->change();
            $table->date('marks_entry_deadline')->nullable()->change();
        });

        // Change status enum to include all values and make nullable with default.
        // MySQL-only DDL; on sqlite the column is plain text.
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE sequences MODIFY COLUMN status ENUM('draft','active','completed','published') DEFAULT 'draft'");
        }

        // Add back a simpler unique on just name
        Schema::table('sequences', function (Blueprint $table) {
            $table->unique('name');
        });
    }

    public function down(): void
    {
        Schema::table('sequences', function (Blueprint $table) {
            $table->dropUnique(['name']);
        });

        Schema::table('sequences', function (Blueprint $table) {
            $table->unsignedBigInteger('term_id')->nullable(false)->change();
            $table->unsignedTinyInteger('sequence_number')->nullable(false)->change();
            $table->date('start_date')->nullable(false)->change();
            $table->date('end_date')->nullable(false)->change();
            $table->foreign('term_id')->references('id')->on('terms')->cascadeOnDelete();
            $table->unique(['term_id', 'sequence_number']);
        });
    }
};
