<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class Sequence extends Model
{
    use Auditable, BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'term_id', 'sequence_number', 'name', 'start_date', 'end_date',
        'weight', 'marks_entry_deadline', 'status',
    ];

    /**
     * The sequences a term actually runs, optionally narrowed to some forms.
     *
     * Not `where('term_id', ...)`: that column exists on the table but is
     * NULL in practice. A sequence is tied to a term through the form_sequence
     * pivot, which is also where the result calculator reads it from, and a
     * school may run different sequences for different forms.
     *
     * @param  array<int, int>|\Illuminate\Support\Collection<int, int>|null  $formIds
     * @return \Illuminate\Database\Eloquent\Collection<int, static>
     */
    public static function forTerm(int $termId, $formIds = null)
    {
        $ids = \Illuminate\Support\Facades\DB::table('form_sequence')
            ->where('term_id', $termId)
            ->when($formIds !== null && count($formIds) > 0, fn ($q) => $q->whereIn('form_id', $formIds))
            ->distinct()
            ->pluck('sequence_id');

        return static::whereIn('id', $ids)
            ->orderBy('sequence_number')
            ->orderBy('id')
            ->get();
    }

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
