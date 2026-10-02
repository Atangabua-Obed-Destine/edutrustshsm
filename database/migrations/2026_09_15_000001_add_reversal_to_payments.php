<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A payment recorded in error could not be undone.
 *
 * Recording a payment moves money through the fee, any payment plan, any
 * over-payment credit, the payment account and the ledger. There was no way to
 * put all of that back — only to delete rows by hand, which leaves the other
 * places disagreeing. A reversal is itself a record, so the payment is kept and
 * marked, with who reversed it and why.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->timestamp('reversed_at')->nullable()->after('verification_status');
            $table->foreignId('reversed_by')->nullable()->after('reversed_at')->constrained('users')->nullOnDelete();
            $table->string('reversal_reason', 500)->nullable()->after('reversed_by');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE payments MODIFY COLUMN verification_status ENUM('pending','verified','rejected','reversed') NOT NULL DEFAULT 'verified'");
            DB::statement("ALTER TABLE parent_payment_submissions MODIFY COLUMN status ENUM('pending','approved','rejected','reversed') NOT NULL DEFAULT 'pending'");
        } else {
            // SQLite enforces an enum as a CHECK constraint, so the new state has
            // to be allowed by rebuilding the column rather than altering a list.
            Schema::table('payments', function (Blueprint $table) {
                $table->string('verification_status', 20)->default('verified')->change();
            });
            Schema::table('parent_payment_submissions', function (Blueprint $table) {
                $table->string('status', 20)->default('pending')->change();
            });
        }
    }

    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("UPDATE payments SET verification_status = 'rejected' WHERE verification_status = 'reversed'");
            DB::statement("ALTER TABLE payments MODIFY COLUMN verification_status ENUM('pending','verified','rejected') NOT NULL DEFAULT 'verified'");
            DB::statement("UPDATE parent_payment_submissions SET status = 'rejected' WHERE status = 'reversed'");
            DB::statement("ALTER TABLE parent_payment_submissions MODIFY COLUMN status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending'");
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reversed_by');
            $table->dropColumn(['reversed_at', 'reversal_reason']);
        });
    }
};
