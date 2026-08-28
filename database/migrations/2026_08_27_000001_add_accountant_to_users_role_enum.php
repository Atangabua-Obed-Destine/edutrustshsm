<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const WITH_ACCOUNTANT = "'super_admin','admin','accountant','teacher','staff','parent','student'";
    private const WITHOUT_ACCOUNTANT = "'super_admin','admin','teacher','staff','parent','student'";

    /**
     * `accountant` was seeded as an RBAC role and is required by the finance,
     * budget, OHADA and payroll route groups (role:super_admin,admin,accountant),
     * but was never a legal value of the users.role enum — so no user could ever
     * hold it and those 138 routes were unreachable.
     *
     * Only MySQL/MariaDB enforce ENUM here; on sqlite (used by the test suite)
     * the column is plain text and already accepts the value.
     */
    public function up(): void
    {
        if (! $this->isMysql()) {
            return;
        }

        DB::statement('ALTER TABLE users MODIFY COLUMN role ENUM('.self::WITH_ACCOUNTANT.') NOT NULL');
    }

    public function down(): void
    {
        if (! $this->isMysql()) {
            return;
        }

        // Demote any accountants before narrowing the column again.
        DB::table('users')->where('role', 'accountant')->update(['role' => 'staff']);

        DB::statement('ALTER TABLE users MODIFY COLUMN role ENUM('.self::WITHOUT_ACCOUNTANT.') NOT NULL');
    }

    private function isMysql(): bool
    {
        return in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true);
    }
};
