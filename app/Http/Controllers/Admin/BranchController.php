<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\User;
use App\Services\BranchProvisioningService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BranchController extends Controller
{
    public function __construct(private BranchProvisioningService $provisioning)
    {
    }

    public function index()
    {
        $branches = Branch::withCount('users')->with('users:id')->orderBy('name')->get();
        $staff = User::orderBy('first_name')->get(['id', 'first_name', 'last_name', 'role']);

        return view('admin.branches.index', compact('branches', 'staff'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'code' => ['required', 'string', 'max:20', 'unique:branches,code'],
        ]);

        $branch = Branch::create([
            'name' => $validated['name'],
            'code' => strtoupper($validated['code']),
            'slug' => Str::slug($validated['name']),
            'is_active' => true,
        ]);

        // Seed the branch's own settings (and, in later phases, its reference data).
        $this->provisioning->provision($branch);

        // The creator (and every super_admin) can already reach it; attach creator explicitly.
        $branch->users()->syncWithoutDetaching([auth()->id()]);

        AuditLog::log('created', Branch::class, $branch->id, null, $branch->toArray());

        return redirect()->route('admin.branches.index')
            ->with('success', __('Branch ":name" created and provisioned.', ['name' => $branch->name]));
    }

    public function update(Request $request, Branch $branch)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'code' => ['required', 'string', 'max:20', 'unique:branches,code,' . $branch->id],
            'is_active' => ['required', 'boolean'],
        ]);

        $old = $branch->toArray();
        $branch->update([
            'name' => $validated['name'],
            'code' => strtoupper($validated['code']),
            'is_active' => $validated['is_active'],
        ]);

        AuditLog::log('updated', Branch::class, $branch->id, $old, $branch->toArray());

        return redirect()->route('admin.branches.index')->with('success', __('Branch updated.'));
    }

    /** Assign which users may access a branch. */
    public function assignUsers(Request $request, Branch $branch)
    {
        $validated = $request->validate([
            'user_ids' => ['array'],
            'user_ids.*' => ['exists:users,id'],
        ]);

        $branch->users()->sync($validated['user_ids'] ?? []);

        return redirect()->route('admin.branches.index')
            ->with('success', __('Branch access updated for ":name".', ['name' => $branch->name]));
    }
}
