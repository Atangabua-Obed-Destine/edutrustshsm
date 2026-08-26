<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_settings', function (Blueprint $table) {
            $table->id();
            $table->string('school_name');
            $table->string('school_short_name')->nullable();
            $table->string('school_code', 10);
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('region')->nullable();
            $table->string('po_box')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('logo')->nullable();
            $table->string('motto')->nullable();
            $table->string('currency', 10)->default('XAF');
            $table->string('student_id_prefix', 20)->default('LCC');
            $table->string('receipt_prefix', 20)->default('RCP');
            $table->unsignedTinyInteger('max_terms_per_session')->default(3);
            $table->unsignedTinyInteger('max_sequences_per_term')->default(2);
            $table->decimal('pass_mark', 4, 1)->default(10.0);
            $table->decimal('promotion_threshold', 4, 1)->default(10.0);
            $table->unsignedTinyInteger('min_attendance_percent')->default(85);
            $table->decimal('max_mark', 4, 1)->default(20.0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_settings');
    }
};
