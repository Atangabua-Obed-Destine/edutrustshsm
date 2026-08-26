<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::withCount(['permissions', 'users'])->orderBy('is_system', 'desc')->orderBy('name')->get();
        return view('admin.roles.index', compact('roles'));
    }

    public function create()
    {
        $permissionGroups = Permission::allGrouped();
        $totalPermissions = Permission::count();

        return view('admin.roles.create', compact('permissionGroups', 'totalPermissions'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);

        $name = strtolower(str_replace(' ', '_', trim($validated['display_name'])));

        // Ensure uniqueness
        if (Role::where('name', $name)->exists()) {
            return back()->withInput()->with('error', __('A role with this name already exists.'));
        }

        $role = Role::create([
            'name' => $name,
            'display_name' => $validated['display_name'],
            'description' => $validated['description'] ?? null,
            'is_system' => false,
        ]);

        $role->permissions()->sync($validated['permissions'] ?? []);

        return redirect()->route('admin.roles.index')->with('success', __('Role ":name" created successfully.', ['name' => $role->display_name]));
    }

    public function show(Role $role)
    {
        $role->load('permissions', 'users');
        $permissionGroups = $role->permissions->groupBy('group_name');

        return view('admin.roles.show', compact('role', 'permissionGroups'));
    }

    public function edit(Role $role)
    {
        $permissionGroups = Permission::allGrouped();
        $totalPermissions = Permission::count();
        $rolePermissionIds = $role->permissions()->pluck('permissions.id')->toArray();

        return view('admin.roles.edit', compact('role', 'permissionGroups', 'totalPermissions', 'rolePermissionIds'));
    }

    public function update(Request $request, Role $role)
    {
        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);

        $name = strtolower(str_replace(' ', '_', trim($validated['display_name'])));

        if (Role::where('name', $name)->where('id', '!=', $role->id)->exists()) {
            return back()->withInput()->with('error', __('A role with this name already exists.'));
        }

        // Don't rename system roles
        if (!$role->is_system) {
            $role->name = $name;
        }

        $role->display_name = $validated['display_name'];
        $role->description = $validated['description'] ?? null;
        $role->save();

        $role->permissions()->sync($validated['permissions'] ?? []);

        return redirect()->route('admin.roles.index')->with('success', __('Role ":name" updated successfully.', ['name' => $role->display_name]));
    }

    public function destroy(Role $role)
    {
        if ($role->is_system) {
            return back()->with('error', __('System roles cannot be deleted.'));
        }

        if ($role->users()->exists()) {
            return back()->with('error', __('Cannot delete — this role is assigned to :count user(s). Reassign them first.', ['count' => $role->users()->count()]));
        }

        $name = $role->display_name;
        $role->permissions()->detach();
        $role->delete();

        return redirect()->route('admin.roles.index')->with('success', __('Role ":name" deleted.', ['name' => $name]));
    }
}
