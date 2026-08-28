<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A template for a journal entry that repeats on a schedule.
 */
class RecurringJournalEntry extends Model
{
    use Auditable, BelongsToBranch;

    protected $fillable = [
        'branch_id', 'title', 'description', 'frequency',
        'start_date', 'end_date', 'next_run_date', 'last_run_date',
        'runs_generated', 'auto_post', 'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'next_run_date' => 'date',
            'last_run_date' => 'date',
            'auto_post' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function lines()
    {
        return $this->hasMany(RecurringJournalEntryLine::class)->orderBy('line_number');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** Templates whose next run has arrived. */
    public function scopeDue($query, ?string $asOf = null)
    {
        $asOf = $asOf ?: now()->toDateString();

        return $query->active()
            ->whereDate('next_run_date', '<=', $asOf)
            ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $asOf));
    }

    public function totalDebit(): float
    {
        return (float) $this->lines->sum('debit');
    }

    public function totalCredit(): float
    {
        return (float) $this->lines->sum('credit');
    }

    /** A template that does not balance can never produce a postable entry. */
    public function isBalanced(): bool
    {
        return abs($this->totalDebit() - $this->totalCredit()) < 0.01;
    }

    /** The run date following a given one. */
    public function advanceFrom(Carbon $date): Carbon
    {
        return match ($this->frequency) {
            'weekly' => $date->copy()->addWeek(),
            'quarterly' => $date->copy()->addMonthsNoOverflow(3),
            'yearly' => $date->copy()->addYearNoOverflow(),
            // addMonthsNoOverflow keeps the 31st from sliding into the 1st or
            // 2nd of the following month.
            default => $date->copy()->addMonthNoOverflow(),
        };
    }

    public function hasFinished(): bool
    {
        return $this->end_date !== null && $this->next_run_date->gt($this->end_date);
    }
}
