<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which school levels this installation runs. Left NULL on purpose so a
     * fresh install must explicitly choose a mode (the setup modal enforces it).
     */
    public function up(): void
    {
        Schema::table('school_settings', function (Blueprint $table) {
            $table->enum('school_level_mode', ['nursery_primary', 'secondary', 'both'])
                ->nullable()->after('motto');
        });
    }

    public function down(): void
    {
        Schema::table('school_settings', function (Blueprint $table) {
            $table->dropColumn('school_level_mode');
        });
    }
};
