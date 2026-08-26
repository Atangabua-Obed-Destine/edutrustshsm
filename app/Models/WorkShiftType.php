<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class WorkShiftType extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'branch_id','title', 'slug', 'description', 'status'];

    protected function casts(): array
    {
        return ['status' => 'boolean'];
    }

    public function staffs()
    {
        return $this->hasMany(User::class, 'work_shift_id');
    }
}
