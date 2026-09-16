<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staff notes and staff ID card verification.
 *
 * The reference system keeps dated notes against a staff member and prints
 * staff ID cards that can be verified. It verifies by staff ID on a public
 * page, which lets anyone walk the IDs and read who works at the school; here
 * each card carries a random token instead, so a card can be checked but the
 * staff list cannot be enumerated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 191);
            $table->text('note');
            // Private disk: staff notes are HR records, not public files.
            $table->string('attachment')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('id_card_token', 40)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['id_card_token']);
            $table->dropColumn('id_card_token');
        });

        Schema::dropIfExists('staff_notes');
    }
};
