<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Adds only the EdutrustPay permissions, and attaches them only where they
 * belong.
 *
 * A separate seeder on purpose. RolesAndPermissionsSeeder ends with `sync()`
 * calls that REPLACE every role's permission set, so running it on a live
 * install would silently discard any customisation somebody has made since.
 * This one uses firstOrCreate and syncWithoutDetaching: it can be run on a
 * running system without taking anything away.
 */
class EdutrustPayPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = collect(['view', 'edit', 'test'])->map(
            fn (string $action) => Permission::firstOrCreate(
                ['name' => 'edutrustpay-reporting.'.$action],
                ['display_name' => ucfirst($action), 'group_name' => 'EdutrustPay Reporting']
            )
        );

        /*
         * Only super_admin and admin, deliberately.
         *
         * These credentials decide whose figures reach the body, and pasting the
         * wrong ones stops reporting without any visible error here — from the
         * console the school simply goes quiet. That is an administrative act,
         * not part of the accountant's daily work, so it is not granted to the
         * finance roles by default. Grant it explicitly if a bursar is the person
         * who will actually hold the credentials.
         */
        foreach (['super_admin', 'admin'] as $roleName) {
            $role = Role::where('name', $roleName)->first();

            if ($role) {
                $role->permissions()->syncWithoutDetaching($permissions->pluck('id'));
            }
        }

        $this->command?->info('EdutrustPay permissions added to super_admin and admin.');
    }
}
