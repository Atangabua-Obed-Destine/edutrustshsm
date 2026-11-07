<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fees a parent paid before the school moved to this system. Recorded so
 * the pupil's balance is right, but the money was received, banked and
 * booked in the old system: it is not a cash receipt here.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE payments MODIFY COLUMN payment_method ENUM('cash','bank_transfer','mtn_momo','orange_money','edutrustpay','student_credit','prior_system') NOT NULL");
        }
    }

    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE payments MODIFY COLUMN payment_method ENUM('cash','bank_transfer','mtn_momo','orange_money','edutrustpay','student_credit') NOT NULL");
        }
    }
};
