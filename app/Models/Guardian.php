<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Guardian extends Authenticatable
{
    use Auditable, Notifiable, BelongsToBranch;

    protected $fillable = [
        'branch_id', 'user_id',
        'father_name', 'father_phone', 'father_email', 'father_occupation', 'father_address',
        'mother_name', 'mother_phone', 'mother_email', 'mother_occupation', 'mother_address',
        'guardian_name', 'guardian_relationship', 'guardian_phone', 'guardian_email',
        'emergency_contact_name', 'emergency_contact_relationship', 'emergency_contact_phone',
        // Portal auth
        'login_email', 'password', 'email_verified_at',
        'portal_access', 'invite_token', 'invite_expires_at', 'last_login_at',
    ];

    protected $hidden = [
        'password', 'remember_token', 'invite_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'invite_expires_at' => 'datetime',
            'last_login_at' => 'datetime',
            'portal_access' => 'boolean',
            'password' => 'hashed',
        ];
    }

    /**
     * Best display name for the account holder — prefers the explicit guardian name,
     * then father, then mother, then a generic fallback.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->guardian_name
            ?: $this->father_name
            ?: $this->mother_name
            ?: __('Parent / Guardian');
    }

    /** The email used to receive the portal invite (canonical contact). */
    public function getPrimaryEmailAttribute(): ?string
    {
        return $this->login_email
            ?: $this->guardian_email
            ?: $this->father_email
            ?: $this->mother_email;
    }

    /** The best phone to reach this guardian on. */
    public function getPrimaryPhoneAttribute(): ?string
    {
        return $this->guardian_phone
            ?: $this->father_phone
            ?: $this->mother_phone;
    }

    /** Whether the parent has completed the claim flow and can log in. */
    public function canAccessPortal(): bool
    {
        return $this->portal_access && ! empty($this->password);
    }

    /** Whether the stored invite token is still valid. */
    public function inviteIsValid(): bool
    {
        return ! empty($this->invite_token)
            && $this->invite_expires_at
            && $this->invite_expires_at->isFuture();
    }

    public function user() { return $this->belongsTo(User::class); }
    public function students() { return $this->hasMany(Student::class); }
}
