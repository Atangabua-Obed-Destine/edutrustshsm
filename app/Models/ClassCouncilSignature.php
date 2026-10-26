<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

/**
 * Who sat on the council.
 *
 * Kept as a name and a role rather than only a user id, because the people
 * who sit on a class council are not all system users — a parent
 * representative or an external examiner may sign the minutes.
 */
class ClassCouncilSignature extends Model
{
    use BelongsToBranch;

    protected $fillable = ['branch_id', 'class_council_id', 'user_id', 'name', 'role', 'signed_at'];

    protected function casts(): array
    {
        return ['signed_at' => 'datetime'];
    }

    public function council()
    {
        return $this->belongsTo(ClassCouncil::class, 'class_council_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
