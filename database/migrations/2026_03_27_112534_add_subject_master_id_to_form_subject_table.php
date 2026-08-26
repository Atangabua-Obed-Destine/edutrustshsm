<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('form_subject', function (Blueprint $table) {
            $table->foreignId('subject_master_id')->nullable()->after('type')
                ->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('form_subject', function (Blueprint $table) {
            $table->dropForeign(['subject_master_id']);
            $table->dropColumn('subject_master_id');
        });
    }
};
