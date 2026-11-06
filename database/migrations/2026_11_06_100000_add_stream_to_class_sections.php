<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A Sixth Form class is one stream: Lower Sixth Science, Upper Sixth Arts.
     * Saying so on the class lets every way into it (registration, the
     * spreadsheet import, term moves, promotion) give the pupil that stream,
     * and so its subjects and fees. Left empty, a class takes pupils of any
     * stream, each chosen on their own, as before.
     */
    public function up(): void
    {
        Schema::table('class_sections', function (Blueprint $table) {
            $table->foreignId('stream_id')->nullable()->after('form_id')->constrained('streams')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('class_sections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stream_id');
        });
    }
};
