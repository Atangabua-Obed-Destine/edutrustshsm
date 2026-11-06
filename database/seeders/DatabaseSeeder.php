<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    // Model events stay on: the one-branch rule (BelongsToBranch) stamps every
    // seeded class, subject and fee category with the school's branch. With
    // them off, a fresh install saved all of it with no branch and every
    // screen hid it.

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // Must run first: authorization is enforced through these tables, so
            // a fresh install without them locks out everyone but super_admin.
            RolesAndPermissionsSeeder::class,
            DefaultDataSeeder::class,
            PaymentAccountTypeSeeder::class,
            OhadaChartOfAccountsSeeder::class,
        ]);
    }
}
