<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class StaffBankAccount extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'user_id', 'bank_name', 'account_name', 'account_number',
        'bank_branch', 'ifsc_code', 'is_default',
    ];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
