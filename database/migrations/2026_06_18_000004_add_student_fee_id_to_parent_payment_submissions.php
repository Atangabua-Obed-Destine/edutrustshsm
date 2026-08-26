<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parent_payment_submissions', function (Blueprint $table) {
            // Optional: the specific fee the parent intends this payment to cover.
            // When set, approval allocates to this fee first (then FIFO for any remainder).
            $table->foreignId('student_fee_id')->nullable()->after('student_enrollment_id')
                ->constrained('student_fees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('parent_payment_submissions', function (Blueprint $table) {
            $table->dropForeign(['student_fee_id']);
            $table->dropColumn('student_fee_id');
        });
    }
};
