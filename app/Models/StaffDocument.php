<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class StaffDocument extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'branch_id','user_id', 'title', 'file'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
