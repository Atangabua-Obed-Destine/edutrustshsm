<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A boarder in a bed. Open while they sleep there; closed, never deleted, when
 * they move or leave, so the house keeps its history of who slept where.
 */
class BoardingAllocation extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'branch_id', 'student_id', 'boarding_room_id', 'bed', 'start_date', 'end_date',
        'end_reason', 'notes', 'created_by', 'ended_by',
    ];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(BoardingRoom::class, 'boarding_room_id');
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNull('end_date');
    }
}
