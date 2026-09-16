<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

/** A dated note kept against a staff member's record. */
class StaffNote extends Model
{
    use Auditable, BelongsToBranch;

    protected $fillable = ['branch_id', 'user_id', 'title', 'note', 'attachment', 'created_by'];

    public function staff()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
