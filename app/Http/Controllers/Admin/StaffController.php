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

        $departments = Department::orderBy('name')->get();
        $designations = Designation::orderBy('title')->get();
        $workShifts = WorkShiftType::orderBy('title')->get();

        $list = $this->staffList($request)->filters([
            __('Department') => $request->department_id ? $departments->firstWhere('id', (int) $request->department_id)?->name : null,
            __('Designation') => $request->designation_id ? $designations->firstWhere('id', (int) $request->designation_id)?->title : null,
            __('Role') => $request->role ? __(ucfirst((string) $request->role)) : null,
            __('Contract Type') => $request->contract_type,
            __('Work Shift') => $request->work_shift_id ? $workShifts->firstWhere('id', (int) $request->work_shift_id)?->title : null,
        ]);

        if ($download = $list->export($query)) {
            return $download;
        }

        $staff = $list->paginate($query);

        return view('admin.hr.staff.index', [
            'staff' => $staff,
            'departments' => $departments,
            'designations' => $designations,
            'workShifts' => $workShifts,
            'list' => $list,
        ]);
    }

    /** The staff list's columns: what sorts, and what an export writes. */
    private function staffList(Request $request): \App\Support\Listing
    {
        $department = fn () => DB::table('departments')->select('name')->whereColumn('departments.id', 'users.department_id')->limit(1);
        $designation = fn () => DB::table('designations')->select('title')->whereColumn('designations.id', 'users.designation_id')->limit(1);

        return new \App\Support\Listing($request, 'staff', __('Staff List'), [
            'staff_id' => ['label' => __('Staff ID'), 'sort' => 'users.staff_id', 'value' => fn ($s) => $s->staff_id],
            'name' => ['label' => __('Name'), 'sort' => ['users.last_name', 'users.first_name'], 'value' => fn ($s) => $s->full_name],
            'department' => ['label' => __('Department'), 'sort' => fn ($q, $dir) => $q->orderBy($department(), $dir),
                'value' => fn ($s) => $s->department?->name],
            'designation' => ['label' => __('Designation'), 'sort' => fn ($q, $dir) => $q->orderBy($designation(), $dir),
                'value' => fn ($s) => $s->designation?->title],
            'salary_type' => ['label' => __('Salary Type'), 'sort' => 'users.salary_type',
                'value' => fn ($s) => $s->salary_type == 1 ? __('Fixed') : __('Hourly')],
            'basic_salary' => ['label' => __('Basic Salary'), 'sort' => 'users.basic_salary', 'money' => true, 'value' => fn ($s) => $s->basic_salary],
            'status' => ['label' => __('Status'), 'sort' => 'users.is_active', 'value' => fn ($s) => $s->is_active ? __('Active') : __('Inactive')],
        ], default: 'staff_id');
    }

    public function create()
    {
        return view('admin.hr.staff.create', $this->formData());
    }

    public function store(Request $request)
    {
        $data = $this->validateStaff($request);

        // Every account left without a password used to open with the word
        // "password". Generate a temporary one instead and show it once.
        $temporary = $request->filled('password') ? null : \Illuminate\Support\Str::password(10, symbols: false);

        $user = DB::transaction(function () use ($data, $request, $temporary) {
            $roles = $data['roles'];
            unset($data['roles']);
            $user = new User($data + ['role' => User::primaryRoleFor($roles)]);
            $user->staff_id = ($data['staff_id'] ?? null) ?: $this->generateStaffId();
            $user->password = Hash::make($request->input('password') ?: $temporary);
            if ($request->hasFile('profile_photo')) {
                $user->profile_photo = $request->file('profile_photo')->store('staff/photos', 'public');
            }
            $user->save();
            $user->joinWorkingBranch();
            $this->syncRoles($user, $roles);
            $this->syncBankAccounts($user, $request);
            return $user;
        });


        $message = $temporary
            ? __('Staff member created. Their temporary password is :password — give it to them privately; it is not shown again.', ['password' => $temporary])
            : __('Staff member created.');

        return redirect()->route('admin.staff.index')->with('success', $message);
    }

    public function show(User $staff)
    {
        $staff->load(['department', 'designation', 'workShift', 'bankAccounts', 'payrolls', 'staffNotes.createdBy']);
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
            $roles = $data['roles'];
            unset($data['roles']);
            $staff->update($data);
            $this->syncRoles($staff, $roles);
            if ($request->filled('password')) {
                $staff->update(['password' => Hash::make($request->password)]);
            }
            $this->syncBankAccounts($staff, $request);
        });


        return redirect()->route('admin.staff.index')->with('success', __('Staff member updated.'));
    }

    public function destroy(User $staff)
    {
        // A teacher's marks submissions cascade off their user row, and the
        // receipts and marks they signed would lose their author.
        $signed = \Illuminate\Support\Facades\DB::table('marks_submissions')->where('teacher_id', $staff->id)->exists()
            || \Illuminate\Support\Facades\DB::table('payments')->where('received_by', $staff->id)->exists()
            || \Illuminate\Support\Facades\DB::table('marks')->where('entered_by', $staff->id)->exists();

        if ($signed) {
            return back()->with('error', __('This staff member has entered marks or received payments, so their record cannot be deleted. Deactivate the account instead.'));
        }

        $old = $staff->toArray();
        $staff->delete();
        return back()->with('success', __('Staff member deleted.'));
    }

    /** The roles this screen gives; administrators are made on User Accounts. */
    public const ROLES = ['teacher', 'accountant', 'receptionist', 'librarian', 'staff'];

    /**
     * Put the person in the roles ticked, keeping any this screen does not
     * offer (an administrator edited here stays an administrator), and write
     * the most senior one on the account.
     */
    private function syncRoles(User $user, array $ticked): void
    {
        $kept = $user->roles()->whereNotIn('name', self::ROLES)->pluck('roles.id');
        $chosen = \App\Models\Role::whereIn('name', $ticked)->pluck('id');
        $user->roles()->sync($kept->merge($chosen)->unique()->all());
        $user->forgetPermissions();

        $names = $user->roles()->pluck('name')->all();
        $user->forceFill(['role' => User::primaryRoleFor($names)])->save();
    }

    private function formData(): array
    {
        return [
            'roleChoices' => \App\Models\Role::whereIn('name', self::ROLES)->get()->sortBy(fn ($r) => array_search($r->name, self::ROLES))->values(),
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
            // Several roles, as in the reference system: a teacher may also be
            // the bursar or keep the front desk.
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['in:'.implode(',', self::ROLES)],
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
