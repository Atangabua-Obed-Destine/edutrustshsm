<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Correcting a fee's amount or due date, and moving money between a pupil's
 * fees. Granted to whoever could already remove a fee, which is the same
 * trust: a school that gave the bursar that keeps the same people in charge.
 */
return new class extends Migration
{
    private array $names = ['assignment-history.edit' => 'Edit', 'assignment-history.transfer' => 'Transfer'];

    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('permissions')) {
            return;
        }

        $holders = DB::table('role_permission')
            ->join('permissions', 'permissions.id', '=', 'role_permission.permission_id')
            ->where('permissions.name', 'assignment-history.delete')
            ->pluck('role_permission.role_id');

        foreach ($this->names as $name => $label) {
            $id = DB::table('permissions')->where('name', $name)->value('id')
                ?? DB::table('permissions')->insertGetId([
                    'name' => $name, 'display_name' => $label, 'group_name' => 'Assignment History',
                    'created_at' => now(), 'updated_at' => now(),
                ]);

            foreach ($holders as $roleId) {
                DB::table('role_permission')->insertOrIgnore([
                    'role_id' => $roleId, 'permission_id' => $id, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('name', array_keys($this->names))->delete();
    }
};
