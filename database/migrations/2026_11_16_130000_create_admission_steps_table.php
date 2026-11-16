<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The steps an application goes through before the school decides, as in the
 * reference system: received, documents checked, entrance test or interview.
 * Each is signed off by someone, or waived with a reason, or sent back with a
 * reason. Append-only: a step sent back keeps the sign-off it had, the way a
 * paper file keeps its crossings-out.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('admission_application_id')->constrained('admission_applications')->cascadeOnDelete();
            $table->string('step', 20);
            $table->string('outcome', 12);
            $table->text('note')->nullable();
            $table->decimal('score', 5, 2)->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at');
            $table->timestamps();
            $table->index(['admission_application_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_steps');
    }
};
