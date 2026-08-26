<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timetable_entries', function (Blueprint $table) {
            $table->time('start_time')->nullable()->after('day_of_week');
            $table->time('end_time')->nullable()->after('start_time');
            $table->unsignedBigInteger('timetable_slot_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('timetable_entries', function (Blueprint $table) {
            $table->dropColumn(['start_time', 'end_time']);
        });
    }
};
