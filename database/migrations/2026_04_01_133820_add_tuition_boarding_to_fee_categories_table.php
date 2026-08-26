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
        Schema::table('fee_categories', function (Blueprint $table) {
            $table->boolean('is_tuition')->default(false)->after('is_refundable');
            $table->boolean('is_boarding')->default(false)->after('is_tuition');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fee_categories', function (Blueprint $table) {
            $table->dropColumn(['is_tuition', 'is_boarding']);
        });
    }
};
