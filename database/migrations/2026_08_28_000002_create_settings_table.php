<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A per-branch key/value store for the settings that do not deserve their
     * own column on school_settings: receipt and report-card templates, mail and
     * SMS credentials, ID-card and payslip options.
     *
     * The reference system solved this with 22 dedicated *SettingController
     * classes over 25 single-row tables, none of them tenant-aware. One typed
     * key/value table plus tabs on the existing settings screen covers the same
     * ground without the sprawl.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
            $table->string('group', 50)->index();
            $table->string('key', 100);
            $table->text('value')->nullable();
            $table->string('type', 20)->default('string'); // string|bool|int|float|json
            $table->boolean('is_encrypted')->default(false);
            $table->timestamps();

            $table->unique(['branch_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
