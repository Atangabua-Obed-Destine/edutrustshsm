<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class Sequence extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'term_id', 'sequence_number', 'name', 'start_date', 'end_date',
        'weight', 'marks_entry_deadline', 'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'marks_entry_deadline' => 'date',
            'weight' => 'decimal:2',
        ];
    }

    public function term()
    {
        return $this->belongsTo(Term::class);
    }

    /** Marks recorded against this sequence (guards deletion). */
    public function marks()
    {
        return $this->hasMany(Mark::class);
    }

    public function forms()
    {
        return $this->belongsToMany(Form::class, 'form_sequence');
    }
}
