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
        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete()->after('student_id');
            $table->string('region_of_origin', 100)->nullable()->after('place_of_birth');
            $table->string('town', 100)->nullable()->after('home_address');
            $table->string('primary_certificate')->nullable()->after('birth_certificate');
            $table->string('gce_ol_certificate')->nullable()->after('primary_certificate');
            $table->string('medical_certificate')->nullable()->after('transfer_certificate');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->string('student_id', 30)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropForeign(['batch_id']);
            $table->dropColumn(['batch_id', 'region_of_origin', 'town', 'primary_certificate', 'gce_ol_certificate', 'medical_certificate']);
        });

        Schema::table('students', function (Blueprint $table) {
            $table->string('student_id', 20)->change();
        });
    }
};
