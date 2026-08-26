<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // ── Define all permissions grouped by module ──
        $groups = [
            'Dashboard' => ['view'],
            'Academic Session' => ['view', 'create', 'edit', 'delete'],
            'Term' => ['view', 'create', 'edit', 'delete'],
            'Exam Sequence' => ['view', 'create', 'edit', 'delete'],
            'Form' => ['view', 'create', 'edit', 'delete'],
            'Stream' => ['view', 'create', 'edit', 'delete'],
            'Subject' => ['view', 'create', 'edit', 'delete'],
            'Subject Enrollment' => ['view', 'enroll', 'drop'],
            'Sequence Enrollment' => ['view', 'enroll'],
            'Class Section' => ['view', 'create', 'edit', 'delete'],
            'Classroom' => ['view', 'create', 'edit', 'delete'],
            'Batch' => ['view', 'create', 'edit', 'delete'],
            'Admission' => ['view', 'accept', 'reject', 'enrol', 'delete'],
            'ID Card' => ['view', 'print', 'settings'],
            'Student' => ['view', 'create', 'edit', 'delete', 'import', 'export'],
            'Group Enrol' => ['view', 'enrol'],
            'Subject Add/Drop' => ['view', 'add', 'drop'],
            'Bulk Upload' => ['view', 'upload'],
            'Exam Schedule' => ['view', 'create', 'edit', 'delete'],
            'Marks Entry' => ['view', 'enter', 'edit'],
            'Exam Publishing' => ['view', 'publish', 'unpublish'],
            'Report Card' => ['view', 'print'],
            'Fee Category' => ['view', 'create', 'edit', 'delete'],
            'Fee Structure' => ['view', 'create', 'edit', 'delete'],
            'Fee Collection' => ['view', 'collect', 'receipt'],
            'Payment Plan' => ['view', 'create', 'cancel'],
            'Quick Assign Fee' => ['view', 'assign'],
            'Assignment History' => ['view', 'delete'],
            'Fee Discount' => ['view', 'create', 'edit', 'delete'],
            'Fee Report' => ['view', 'export'],
            'Payment' => ['view', 'refund'],
            'Attendance' => ['view', 'mark', 'edit', 'report'],
            'Class Schedule' => ['view', 'create', 'edit', 'delete'],
            'Teacher Routine' => ['view'],
            'Promotion' => ['view', 'process'],
            'Report & Analytics' => ['view', 'export'],
            'Staff' => ['view', 'create', 'edit', 'delete'],
            'Role & Permission' => ['view', 'create', 'edit', 'delete'],
            'School Settings' => ['view', 'edit'],
        ];

        $allPermissions = [];
        foreach ($groups as $group => $actions) {
            foreach ($actions as $action) {
                $slug = strtolower(str_replace([' ', '&', '/'], ['-', 'and', '-'], $group));
                $name = $slug . '.' . $action;
                $allPermissions[] = Permission::firstOrCreate(
                    ['name' => $name],
                    [
                        'display_name' => ucfirst($action),
                        'group_name' => $group,
                    ]
                );
            }
        }

        // ── Create system roles ──
        $superAdmin = Role::firstOrCreate(
            ['name' => 'super_admin'],
            ['display_name' => 'Super Admin', 'description' => 'Full system access with all permissions.', 'is_system' => true]
        );

        $admin = Role::firstOrCreate(
            ['name' => 'admin'],
            ['display_name' => 'Admin', 'description' => 'Administrative access to most features.', 'is_system' => true]
        );

        $accountant = Role::firstOrCreate(
            ['name' => 'accountant'],
            ['display_name' => 'Accountant', 'description' => 'Manages fees, payments, and financial reports.', 'is_system' => true]
        );

        $teacher = Role::firstOrCreate(
            ['name' => 'teacher'],
            ['display_name' => 'Teacher', 'description' => 'Manages marks, attendance, and class activities.', 'is_system' => true]
        );

        $librarian = Role::firstOrCreate(
            ['name' => 'librarian'],
            ['display_name' => 'Librarian', 'description' => 'Manages library resources.', 'is_system' => true]
        );

        $receptionist = Role::firstOrCreate(
            ['name' => 'receptionist'],
            ['display_name' => 'Receptionist', 'description' => 'Handles admissions and front desk operations.', 'is_system' => true]
        );

        // ── Assign permissions ──
        // Super Admin gets ALL permissions
        $superAdmin->permissions()->sync(
            collect($allPermissions)->pluck('id')
        );

        // Admin gets everything except Role & Permission management
        $admin->permissions()->sync(
            collect($allPermissions)->reject(fn ($p) => str_starts_with($p->name, 'role-and-permission.'))->pluck('id')
        );

        // Accountant gets fee-related + dashboard + student view
        $feeGroups = ['Dashboard', 'Fee Category', 'Fee Structure', 'Fee Collection', 'Payment Plan', 'Quick Assign Fee', 'Assignment History', 'Fee Discount', 'Fee Report', 'Payment'];
        $accountant->permissions()->sync(
            collect($allPermissions)->filter(fn ($p) => in_array($p->group_name, $feeGroups) || $p->name === 'student.view')->pluck('id')
        );

        // Teacher gets academic/exam + attendance + student view + dashboard
        $teacherGroups = ['Dashboard', 'Marks Entry', 'Attendance', 'Report Card', 'Exam Schedule', 'Teacher Routine', 'Class Schedule'];
        $teacher->permissions()->sync(
            collect($allPermissions)->filter(fn ($p) => in_array($p->group_name, $teacherGroups) || $p->name === 'student.view' || $p->name === 'subject.view')->pluck('id')
        );

        // Receptionist gets admissions + student create + dashboard
        $receptionistGroups = ['Dashboard', 'Admission', 'Student', 'ID Card'];
        $receptionist->permissions()->sync(
            collect($allPermissions)->filter(fn ($p) => in_array($p->group_name, $receptionistGroups))->pluck('id')
        );
    }
}
