<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class PtaMeeting extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'branch_id', 'title', 'agenda', 'venue',
        'meeting_date', 'minutes_path', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return ['meeting_date' => 'datetime'];
    }

    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }

    public function scopeUpcoming($query)
    {
        return $query->where('status', 'scheduled')->where('meeting_date', '>=', now());
    }
}
