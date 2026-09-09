<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets the reminder command tell "not yet chased" from "chased on Tuesday".
 *
 * Without it a daily schedule would mail the same parent every morning, which
 * is the fastest way to have reminders filtered into a junk folder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_fees', function (Blueprint $table) {
            $table->timestamp('last_reminded_at')->nullable()->after('due_date');
        });
    }

    public function down(): void
    {
        Schema::table('student_fees', function (Blueprint $table) {
            $table->dropColumn('last_reminded_at');
        });
    }
};
