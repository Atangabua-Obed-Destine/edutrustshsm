<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Authorization now runs through the Role/Permission tables, not just the
     * legacy `users.role` string. Any existing user who has a legacy role but no
     * row in `role_user` would suddenly hold zero permissions and be locked out
     * of every screen.
     *
     * This attaches the matching RBAC role (same name) to those users. It is
     * deliberately additive: users who already have RBAC roles are untouched,
     * and a legacy role with no matching RBAC row is skipped rather than
     * guessed at.
     */
    public function up(): void
    {
        $roleIdsByName = DB::table('roles')->pluck('id', 'name');

        $orphans = DB::table('users')
            ->whereNotIn('id', DB::table('role_user')->select('user_id'))
            ->get(['id', 'role']);

        $now = now();
        $rows = [];

        foreach ($orphans as $user) {
            $roleId = $roleIdsByName[$user->role] ?? null;
            if (! $roleId) {
                continue;
            }

            $rows[] = [
                'user_id' => $user->id,
                'role_id' => $roleId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows) {
            DB::table('role_user')->insert($rows);
        }
    }

    public function down(): void
    {
        // Irreversible by design: we cannot tell a backfilled assignment from a
        // deliberate one, and removing a real assignment would lock users out.
    }
};
