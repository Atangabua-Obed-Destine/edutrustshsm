<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($role = $request->input('role')) {
            $query->where('role', $role);
        }

        if ($request->input('status') === 'active') {
            $query->where('is_active', true);
        } elseif ($request->input('status') === 'inactive') {
            $query->where('is_active', false);
        }

        $users = $query->orderBy('last_name')->orderBy('first_name')->paginate(25)->withQueryString();

        $roleCounts = User::selectRaw('role, count(*) as count')->groupBy('role')->pluck('count', 'role');

        return view('admin.users.index', compact('users', 'roleCounts'));
    }

    public function create()
    {
        $roles = Role::orderBy('is_system', 'desc')->orderBy('display_name')->get();
        return view('admin.users.create', compact('roles'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'other_names' => 'nullable|string|max:100',
            'email' => 'required|email|max:255|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'gender' => 'nullable|in:male,female',
            'roles' => 'required|array|min:1',
            'roles.*' => 'integer|exists:roles,id',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $selectedRoles = Role::whereIn('id', $validated['roles'])->get();

        // Derive the primary role enum from the selected roles (used by CheckRole middleware).
        $primaryRole = $this->derivePrimaryRole($selectedRoles);

        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'other_names' => $validated['other_names'] ?? null,
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'role' => $primaryRole,
            'password' => Hash::make($validated['password']),
            'is_active' => true,
        ]);

        $user->roles()->sync($validated['roles']);

        return redirect()->route('admin.users.index')->with('success', 'Staff member created successfully.');
    }

    public function edit(User $user)
    {
        $roles = Role::orderBy('is_system', 'desc')->orderBy('display_name')->get();
        $userRoleIds = $user->roles()->pluck('roles.id')->toArray();
        return view('admin.users.edit', compact('user', 'roles', 'userRoleIds'));
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'other_names' => 'nullable|string|max:100',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => 'nullable|string|max:20',
            'gender' => 'nullable|in:male,female',
            'roles' => 'required|array|min:1',
            'roles.*' => 'integer|exists:roles,id',
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        $selectedRoles = Role::whereIn('id', $validated['roles'])->get();

        // Protect super_admin: cannot strip the super_admin role from a super_admin user.
        if ($user->role === 'super_admin' && !$selectedRoles->contains('name', 'super_admin')) {
            return back()->withInput()->with('error', 'You cannot remove the Super Admin role from a Super Admin user.');
        }

        $primaryRole = $user->role === 'super_admin'
            ? 'super_admin'
            : $this->derivePrimaryRole($selectedRoles);

        $updateData = [
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'other_names' => $validated['other_names'] ?? null,
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'role' => $primaryRole,
        ];

        if (!empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $user->update($updateData);
        $user->roles()->sync($validated['roles']);

        return redirect()->route('admin.users.index')->with('success', 'Staff member updated successfully.');
    }

    /**
     * Map the highest-privilege role from selected roles to the legacy `role` enum
     * (used by the CheckRole middleware and isAdmin/isTeacher helpers).
     */
    private function derivePrimaryRole($selectedRoles): string
    {
        $names = $selectedRoles->pluck('name')->toArray();

        if (in_array('super_admin', $names)) return 'super_admin';
        if (in_array('admin', $names)) return 'admin';
        if (in_array('teacher', $names)) return 'teacher';
        return 'staff';
    }

    public function toggleActive(User $user)
    {
        // Prevent deactivating self
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        $user->update(['is_active' => !$user->is_active]);
        $status = $user->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "Staff member {$status} successfully.");
    }
}
