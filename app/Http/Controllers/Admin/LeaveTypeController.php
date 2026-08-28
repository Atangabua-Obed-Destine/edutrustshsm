<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Controller;
use App\Models\Leave;
use App\Models\LeaveType;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LeaveTypeController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'leave-type';

    public function index()
    {
        return view('admin.hr.leave.types', [
            'types' => LeaveType::withCount('leaves')->orderBy('title')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);

        LeaveType::create($validated + ['slug' => Str::slug($validated['title'])]);

        return back()->with('success', __('Leave type created.'));
    }

    public function update(Request $request, LeaveType $leaveType)
    {
        $leaveType->update($this->validated($request, $leaveType));

        return back()->with('success', __('Leave type updated.'));
    }

    public function destroy(LeaveType $leaveType)
    {
        // Deleting a type would orphan the leave recorded against it, and the
        // balance history with it. Deactivate instead.
        if (Leave::where('leave_type_id', $leaveType->id)->exists()) {
            return back()->with('error', __('This leave type is in use. Deactivate it instead of deleting.'));
        }

        $leaveType->delete();

        return back()->with('success', __('Leave type deleted.'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?LeaveType $existing = null): array
    {
        return $request->validate([
            'title' => [
                'required', 'string', 'max:100',
                Rule::unique('leave_types', 'title')->ignore($existing?->id),
            ],
            // Null or 0 means uncapped.
            'annual_limit' => ['nullable', 'integer', 'min:0', 'max:365'],
            'is_paid' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
