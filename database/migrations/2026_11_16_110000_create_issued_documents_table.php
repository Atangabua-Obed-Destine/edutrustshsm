<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The register of certificates and records the school hands out. Every copy
 * printed is an entry: a serial number in the school's own sequence
 * (LC-2027-0004), a check code anyone can type in or scan, and what the paper
 * said, as it said it, so a forged or altered copy shows up against the
 * register even after the pupil's record has moved on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issued_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('type', 40);
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('serial', 30)->unique();
            $table->string('verify_code', 12)->unique();
            $table->json('details')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at');
            $table->timestamps();
            $table->index(['type', 'issued_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issued_documents');
    }
};
