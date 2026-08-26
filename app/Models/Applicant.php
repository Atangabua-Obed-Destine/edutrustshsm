<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Applicant extends Authenticatable
{
    use Notifiable, BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'first_name', 'last_name', 'email', 'phone', 'password',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function applications()
    {
        return $this->hasMany(AdmissionApplication::class);
    }
}
