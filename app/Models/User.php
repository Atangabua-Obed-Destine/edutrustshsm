<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Auditable;

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'first_name',
        'last_name',
        'other_names',
        'email',
        'password',
        'role',
        'phone',
        'profile_photo',
        'gender',
        'is_active',
        'last_login_at',
        // HR / staff
        'staff_id', 'father_name', 'mother_name', 'date_of_birth', 'nationality',
        'religion', 'marital_status', 'blood_group', 'national_id', 'passport_no',
        'emergency_phone', 'present_address', 'permanent_address',
        'department_id', 'designation_id', 'work_shift_id', 'contract_type',
        'salary_type', 'basic_salary', 'joining_date', 'ending_date',
        'education_level', 'academy', 'graduation_year', 'graduation_field',
        'experience', 'tin_no', 'signature', 'resume', 'joining_letter',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Signing in updates last_login_at, which would otherwise write an audit
     * row on every login. Authentication events belong in their own log.
     *
     * @return array<int, string>
     */
    protected function auditExcluded(): array
    {
        return array_merge(
            ['password', 'remember_token', 'invite_token', 'updated_at', 'created_at', 'last_login_at'],
            $this->hidden ?? []
        );
    }

    /** @var array<int, string>|null Memoized permission names for this request. */
    protected ?array $permissionNames = null;

    /** @var array<int, string>|null */
    protected ?array $roleNames = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'date_of_birth' => 'date',
            'joining_date' => 'date',
            'ending_date' => 'date',
            'basic_salary' => 'decimal:2',
        ];
    }

    // ── HR / staff relations ──
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function designation()
    {
        return $this->belongsTo(Designation::class);
    }

    public function workShift()
    {
        return $this->belongsTo(WorkShiftType::class, 'work_shift_id');
    }

    public function staffNotes()
    {
        return $this->hasMany(StaffNote::class)->latest();
    }

    public function bankAccounts()
    {
        return $this->hasMany(StaffBankAccount::class);
    }

    public function staffDocuments()
    {
        return $this->hasMany(StaffDocument::class);
    }

    public function payrolls()
    {
        return $this->hasMany(Payroll::class);
    }

    public function taxExemptions()
    {
        return $this->hasMany(StaffTaxExemption::class);
    }

    /** Staff = users with a non-admin employment role. */
    public function scopeStaff($query)
    {
        return $query->whereNotIn('role', ['super_admin', 'admin', 'parent', 'student']);
    }

    /**
     * Limit users to the active branch context (or, in All-Branches mode, every
     * accessible branch). Used for branch-aware staff/teacher counts on reports.
     * Users belong to branches via the branch_user pivot.
     */
    public function scopeInBranchContext($query)
    {
        if (! \App\Support\BranchContext::isActive()) {
            return $query;
        }

        $ids = \App\Support\BranchContext::isAllBranches()
            ? \App\Support\BranchContext::accessibleIds()
            : [\App\Support\BranchContext::current()];

        return $query->whereHas('branches', fn ($q) => $q->whereIn('branches.id', $ids));
    }

    public function getIdCardValidityAttribute(): string
    {
        $start = $this->joining_date?->year ?? now()->year;
        $end = $this->ending_date?->year ?? ($start + 4);
        return "{$start}-{$end}";
    }

    public function getFullNameAttribute(): string
    {
        $names = trim($this->first_name . ' ' . ($this->other_names ?? '') . ' ' . $this->last_name);
        return preg_replace('/\s+/', ' ', $names);
    }

    /**
     * Every role this person holds: those ticked on their account and the one
     * named on it. A teacher who is also the bursar holds both, as in the
     * reference system, and each opens its part of the school.
     *
     * @return array<int, string>
     */
    public function roleNames(): array
    {
        return $this->roleNames ??= collect($this->roles()->pluck('name'))
            ->push($this->role)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * The one role written on the account, from the roles held: the most
     * senior. Kept for the reports and lists that count by it; access itself
     * follows every role held.
     *
     * @param  array<int, string>  $names
     */
    public static function primaryRoleFor(array $names): string
    {
        foreach (['super_admin', 'admin', 'accountant', 'teacher'] as $role) {
            if (in_array($role, $names, true)) {
                return $role;
            }
        }

        return 'staff';
    }

    /** Whether this person holds any of the given roles. */
    public function holdsRole(string ...$names): bool
    {
        return array_intersect($names, $this->roleNames()) !== [];
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isAdmin(): bool
    {
        return $this->holdsRole('super_admin', 'admin');
    }

    public function isTeacher(): bool
    {
        return $this->holdsRole('teacher');
    }

    public function isAccountant(): bool
    {
        return in_array($this->role, ['super_admin', 'admin', 'accountant']);
    }

    public function isStaff(): bool
    {
        return in_array($this->role, ['super_admin', 'admin', 'accountant', 'teacher', 'staff']);
    }

    /**
     * The route this user should land on after logging in.
     *
     * Returns null when the role has no portal yet (teacher, parent, student on
     * the web guard) — callers must handle that rather than sending them to the
     * admin dashboard, which is admin-only and would 403 immediately.
     */
    public function homeRoute(): ?string
    {
        return match ($this->role) {
            'super_admin', 'admin', 'teacher' => 'admin.dashboard',
            'accountant' => 'admin.account.income.index',
            'staff' => 'admin.students.index',
            default => null,
        };
    }

    public function classSections()
    {
        return $this->hasMany(ClassSection::class, 'class_teacher_id');
    }

    public function teacherSubjectAssignments()
    {
        return $this->hasMany(TeacherSubjectAssignment::class);
    }

    public function roleModel()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    /**
     * All roles assigned to this user (many-to-many).
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_user')->withTimestamps();
    }

    /**
     * Branches (campuses) this user may access and switch between.
     */
    public function branches()
    {
        return $this->belongsToMany(Branch::class, 'branch_user')->withPivot('is_default')->withTimestamps();
    }

    /**
     * Check if the user has a specific permission via any of their assigned roles.
     */
    public function hasPermission(string $permission): bool
    {
        if ($this->role === 'super_admin') {
            return true;
        }

        return in_array($permission, $this->permissionNames(), true);
    }

    /**
     * Every permission name granted by this user's roles, loaded once per
     * request. Authorization is checked on nearly every nav item and action, so
     * a query per check would add hundreds of round-trips to a single page.
     *
     * @return array<int, string>
     */
    public function permissionNames(): array
    {
        // A staff member created on the HR screen carries the role name but
        // was never attached to the role record, so had no permissions at
        // all. The role named on the account counts as one of its roles.
        return $this->permissionNames ??= $this->roles()
            ->with('permissions:id,name')
            ->get()
            ->pipe(fn ($roles) => $this->role && ! $roles->contains('name', $this->role)
                ? $roles->merge(Role::where('name', $this->role)->whereNotIn('id', $roles->pluck('id'))->with('permissions:id,name')->get())
                : $roles)
            ->pluck('permissions')
            ->flatten()
            ->pluck('name')
            ->unique()
            ->values()
            ->all();
    }

    /** Drop the memoized permission set (after a role change). */
    public function forgetPermissions(): void
    {
        $this->permissionNames = null;
        $this->roleNames = null;
    }

    /**
     * Check if user has any of the given role names.
     */
    public function hasAnyRole(array $roleNames): bool
    {
        return $this->roles()->whereIn('name', $roleNames)->exists();
    }
}
