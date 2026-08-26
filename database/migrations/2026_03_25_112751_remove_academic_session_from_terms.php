<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('terms', function (Blueprint $table) {
            $table->dropForeign(['academic_session_id']);
        });

        Schema::table('terms', function (Blueprint $table) {
            $table->dropUnique(['academic_session_id', 'term_number']);
            $table->dropColumn('academic_session_id');
            $table->unique('term_number');
        });
    }

    public function down(): void
    {
        Schema::table('terms', function (Blueprint $table) {
            $table->dropUnique(['term_number']);
            $table->foreignId('academic_session_id')->nullable()->constrained('academic_sessions')->cascadeOnDelete();
            $table->unique(['academic_session_id', 'term_number']);
        });
    }
};
