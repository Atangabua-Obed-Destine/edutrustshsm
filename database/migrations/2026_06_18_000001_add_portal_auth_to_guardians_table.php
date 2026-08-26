<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guardians', function (Blueprint $table) {
            // Primary login email for the parent portal (mirrors the existing contact emails
            // but is the canonical credential — kept separate so contact info can change freely).
            $table->string('login_email')->nullable()->after('guardian_email');
            $table->string('password')->nullable()->after('login_email');
            $table->rememberToken()->after('password');
            $table->timestamp('email_verified_at')->nullable()->after('remember_token');

            // Admin gate + one-time claim flow.
            $table->boolean('portal_access')->default(false)->after('email_verified_at');
            $table->string('invite_token')->nullable()->unique()->after('portal_access');
            $table->timestamp('invite_expires_at')->nullable()->after('invite_token');
            $table->timestamp('last_login_at')->nullable()->after('invite_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('guardians', function (Blueprint $table) {
            $table->dropColumn([
                'login_email', 'password', 'remember_token', 'email_verified_at',
                'portal_access', 'invite_token', 'invite_expires_at', 'last_login_at',
            ]);
        });
    }
};
