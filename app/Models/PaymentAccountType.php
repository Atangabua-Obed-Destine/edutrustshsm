<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class PaymentAccountType extends Model
{
    use BelongsToBranch;

    protected $fillable = ['branch_id', 'title', 'slug', 'description', 'status'];

    protected function casts(): array
    {
        return ['status' => 'boolean'];
    }

    public function accounts()
    {
        return $this->hasMany(PaymentAccount::class, 'account_type_id');
    }
}
