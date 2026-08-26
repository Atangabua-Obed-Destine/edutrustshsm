<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class DeductionType extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'branch_id','title', 'status'];

    protected function casts(): array
    {
        return ['status' => 'boolean'];
    }
}
