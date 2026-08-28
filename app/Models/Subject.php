<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    use Auditable, BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'name', 'code', 'department_id', 'description', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function forms()
    {
        return $this->belongsToMany(Form::class, 'form_subject')
            ->withPivot('stream_id', 'coefficient', 'type');
    }
}
