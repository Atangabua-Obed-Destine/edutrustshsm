<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\Department;
use App\Models\Designation;
use App\Models\SchoolSetting;
use App\Models\StaffBankAccount;
use App\Models\User;
use App\Models\WorkShiftType;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class StaffController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'staff';

    public function index(Request $request)
    {
        $query = User::staff()->with(['department', 'designation', 'workShift'])
            ->when($request->department_id, fn ($q, $v) => $q->where('department_id', $v))
            ->when($request->designation_id, fn ($q, $v) => $q->where('designation_id', $v))
            ->when($request->role, fn ($q, $v) => $q->where('role', $v))
            ->when($request->contract_type, fn ($q, $v) => $q->where('contract_type', $v))
            ->when($request->work_shift_id, fn ($q, $v) => $q->where('work_shift_id', $v));

        $staff = $query->orderBy('staff_id')->paginate(25)->withQueryString();

        return view('admin.hr.staff.index', [
            'staff' => $staff,
            'departments' => Department::orderBy('name')->get(),
            'designations' => Designation::orderBy('title')->get(),
            'workShifts' => WorkShiftType::orderBy('title')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.hr.staff.create', $this->formData());
    }

    public function store(Request $request)
    {
        $data = $this->validateStaff($request);

        $user = DB::transaction(function () use ($data, $request) {
            $user = new User($data);
            $user->staff_id = $data['staff_id'] ?: $this->generateStaffId();
            $user->password = Hash::make($request->input('password') ?: 'password');
            if ($request->hasFile('profile_photo')) {
                $user->profile_photo = $request->file('profile_photo')->store('staff/photos', 'public');
            }
            $user->save();
            $this->syncBankAccounts($user, $request);
            return $user;
        });


        return redirect()->route('admin.staff.index')->with('success', __('Staff member created.'));
    }

    public function show(User $staff)
    {
        $staff->load(['department', 'designation', 'workShift', 'bankAccounts', 'payrolls']);
        return view('admin.hr.staff.show', compact('staff'));
    }

    public function edit(User $staff)
    {
        return view('admin.hr.staff.edit', $this->formData() + ['staff' => $staff->load('bankAccounts')]);
    }

    public function update(Request $request, User $staff)
    {
        $data = $this->validateStaff($request, $staff->id);
        $old = $staff->toArray();

        DB::transaction(function () use ($staff, $data, $request) {
            if ($request->hasFile('profile_photo')) {
                $data['profile_photo'] = $request->file('profile_photo')->store('staff/photos', 'public');
            }
            unset($data['staff_id']); // immutable after creation
            $staff->update($data);
            if ($request->filled('password')) {
                $staff->update(['password' => Hash::make($request->password)]);
            }
            $this->syncBankAccounts($staff, $request);
        });


        return redirect()->route('admin.staff.index')->with('success', __('Staff member updated.'));
    }

    public function destroy(User $staff)
    {
        $old = $staff->toArray();
        $staff->delete();
        return back()->with('success', __('Staff member deleted.'));
    }

    private function formData(): array
    {
        return [
            'departments' => Department::orderBy('name')->get(),
            'designations' => Designation::orderBy('title')->get(),
            'workShifts' => WorkShiftType::orderBy('title')->get(),
        ];
    }

    private function generateStaffId(): string
    {
        $prefix = SchoolSetting::current()?->school_code ?: 'STF';
        $last = User::where('staff_id', 'like', $prefix . '%')->orderByDesc('staff_id')->first();
        $next = $last ? ((int) preg_replace('/\D/', '', substr($last->staff_id, strlen($prefix)))) + 1 : 1;
        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function syncBankAccounts(User $user, Request $request): void
    {
        $accounts = $request->input('bank_accounts', []);
        $user->bankAccounts()->delete();
        foreach ($accounts as $i => $acc) {
            if (empty($acc['bank_name'])) {
                continue;
            }
            StaffBankAccount::create([
                'user_id' => $user->id,
                'bank_name' => $acc['bank_name'],
                'account_name' => $acc['account_name'] ?? null,
                'account_number' => $acc['account_number'] ?? null,
                'bank_branch' => $acc['bank_branch'] ?? null,
                'is_default' => (string) ($request->input('default_bank')) === (string) $i,
            ]);
        }
    }

    private function validateStaff(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'staff_id' => ['nullable', 'string', 'max:50', 'unique:users,staff_id' . ($id ? ',' . $id : '')],
            'first_name' => ['required', 'string', 'max:191'],
            'last_name' => ['required', 'string', 'max:191'],
            'other_names' => ['nullable', 'string', 'max:191'],
            'father_name' => ['nullable', 'string', 'max:191'],
            'mother_name' => ['nullable', 'string', 'max:191'],
            'email' => ['required', 'email', 'unique:users,email' . ($id ? ',' . $id : '')],
            'phone' => ['nullable', 'string', 'max:50'],
            'emergency_phone' => ['nullable', 'string', 'max:50'],
            'gender' => ['nullable', 'in:male,female'],
            'date_of_birth' => ['nullable', 'date'],
            'national_id' => ['nullable', 'string', 'max:100'],
            'marital_status' => ['nullable', 'string', 'max:50'],
            'role' => ['required', 'in:teacher,staff'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'designation_id' => ['nullable', 'exists:designations,id'],
            'work_shift_id' => ['nullable', 'exists:work_shift_types,id'],
            'contract_type' => ['nullable', 'string', 'max:50'],
            'salary_type' => ['required', 'in:1,2'],
            'basic_salary' => ['required', 'numeric', 'min:0'],
            'joining_date' => ['nullable', 'date'],
            'ending_date' => ['nullable', 'date', 'after_or_equal:joining_date'],
            'tin_no' => ['nullable', 'string', 'max:100'],
            'present_address' => ['nullable', 'string'],
            'permanent_address' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'profile_photo' => ['nullable', 'image', 'max:2048'],
        ]);
    }
}
