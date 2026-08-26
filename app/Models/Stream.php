<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class Stream extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'name', 'code', 'description', 'is_general', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_general' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function forms()
    {
        return $this->belongsToMany(Form::class, 'form_stream');
    }
}
