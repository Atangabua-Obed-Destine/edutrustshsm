<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use Auditable, BelongsToBranch;

    protected $fillable = ['branch_id', 'name'];

    public function subjects()
    {
        return $this->hasMany(Subject::class);
    }
}
