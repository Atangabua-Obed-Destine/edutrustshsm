<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Create the default "Main Branch" and attach all existing data/users to it,
     * so the system keeps working unchanged after enabling multi-branch.
     */
    public function up(): void
    {
        $existing = DB::table('school_settings')->first();

        $branchId = DB::table('branches')->insertGetId([
            'name' => $existing->school_name ?? 'Main Branch',
            'code' => $existing->school_code ?? 'MAIN',
            'slug' => 'main',
            'logo' => $existing->logo ?? null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // The single existing settings row belongs to the Main Branch.
        DB::table('school_settings')->update(['branch_id' => $branchId]);

        // Attach every existing user to the Main Branch (as their default).
        $now = now();
        $rows = DB::table('users')->pluck('id')->map(fn ($uid) => [
            'branch_id' => $branchId,
            'user_id' => $uid,
            'is_default' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        if ($rows) {
            DB::table('branch_user')->insert($rows);
        }
    }

    public function down(): void
    {
        DB::table('branch_user')->truncate();
        DB::table('school_settings')->update(['branch_id' => null]);
        DB::table('branches')->truncate();
    }
};
