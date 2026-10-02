<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The EdutrustPay outbox.
 *
 * Reports are written here first and delivered separately: a school office
 * loses power and may have a hotspot for a few minutes a day, and a month must
 * not go missing because the network happened to be down when it closed. On the
 * console a missing month is indistinguishable from a school that has stopped
 * reporting, and only one of those needs somebody to drive out and look.
 *
 * The signed payload is stored verbatim so a retry sends the SAME BYTES. The
 * signature covers those bytes, so rebuilding on retry would produce a different
 * document — and if the figures had moved meanwhile, a silent restatement.
 *
 * branch_id is part of the identity: this system is multi-branch and a branch is
 * a separate school with its own credentials, so two campuses reporting the same
 * month are two reports, not a duplicate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edutrustpay_outbox', function (Blueprint $table) {
            $table->id();

            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();

            $table->string('kind', 20)->default('report');   // report | heartbeat
            $table->string('period', 7)->nullable();
            $table->unsignedInteger('sequence')->default(1);

            $table->longText('payload');
            $table->string('payload_hash', 64);

            // String rather than enum, per this codebase's convention: enums
            // break under later ALTERs.
            $table->string('status', 20)->default('pending'); // pending | delivered | failed
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            $table->unsignedSmallInteger('last_status_code')->nullable();
            $table->text('last_response')->nullable();

            $table->timestamps();

            $table->unique(['branch_id', 'kind', 'period', 'sequence'], 'etp_outbox_unique');
            $table->index(['status', 'next_attempt_at'], 'etp_outbox_due_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edutrustpay_outbox');
    }
};
