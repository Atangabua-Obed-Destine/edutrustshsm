<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class TimetableSlot extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'period_number', 'start_time', 'end_time', 'type', 'label',
    ];

    public function entries() { return $this->hasMany(TimetableEntry::class); }
}
