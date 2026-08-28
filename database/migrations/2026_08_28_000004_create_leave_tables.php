<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Staff leave.
     *
     * `leave_types.annual_limit` is the maximum days one staff member may take
     * of that type per calendar year; 0 or null means unlimited. The limit is
     * enforced against approved AND pending requests, so stacked applications
     * cannot slip past it together.
     */
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('title', 100);
            $table->string('slug', 100)->unique();
            $table->unsignedSmallInteger('annual_limit')->nullable(); // null / 0 = unlimited
            $table->boolean('is_paid')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('leave_types')->restrictOnDelete();
            $table->date('apply_date');
            $table->date('from_date');
            $table->date('to_date');
            $table->text('reason')->nullable();
            $table->string('attachment')->nullable();
            $table->enum('pay_type', ['paid', 'unpaid'])->default('paid');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();

            // The balance check reads a staff member's leave for one year.
            $table->index(['user_id', 'status', 'from_date']);
            $table->index(['leave_type_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leaves');
        Schema::dropIfExists('leave_types');
    }
};
