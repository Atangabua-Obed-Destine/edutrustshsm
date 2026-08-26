<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add stream_id and drop residence_type from fee_structures
        Schema::table('fee_structures', function (Blueprint $table) {
            $table->foreignId('stream_id')->nullable()->after('form_id')->constrained('streams')->nullOnDelete();
            $table->dropForeign(['academic_session_id']);
            $table->dropUnique('fee_struct_unique');
        });

        Schema::table('fee_structures', function (Blueprint $table) {
            $table->dropColumn('residence_type');
            $table->foreignId('academic_session_id')->nullable()->change();
            $table->foreign('academic_session_id')->references('id')->on('academic_sessions')->nullOnDelete();
            $table->unique(['academic_session_id', 'form_id', 'stream_id', 'fee_category_id'], 'fee_struct_unique');
        });

        // 2. Create fee_breakdowns table
        Schema::create('fee_breakdowns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_structure_id')->constrained('fee_structures')->cascadeOnDelete();
            $table->string('name', 150);
            $table->decimal('amount', 12, 2);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_breakdowns');

        Schema::table('fee_structures', function (Blueprint $table) {
            $table->dropUnique('fee_struct_unique');
            $table->dropForeign(['stream_id']);
            $table->dropColumn('stream_id');
            $table->dropForeign(['academic_session_id']);
        });

        Schema::table('fee_structures', function (Blueprint $table) {
            $table->foreignId('academic_session_id')->nullable(false)->change();
            $table->foreign('academic_session_id')->references('id')->on('academic_sessions')->cascadeOnDelete();
            $table->enum('residence_type', ['day', 'boarding', 'half_boarding'])->after('form_id');
            $table->unique(['academic_session_id', 'form_id', 'residence_type', 'fee_category_id'], 'fee_struct_unique');
        });
    }
};
