<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

/**
 * One attempt at closing a fiscal year: its checklist, its result, and — if it
 * was undone — who reversed it and why.
 */
class YearEndClosing extends Model
{
    use Auditable, BelongsToBranch;

    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'branch_id', 'fiscal_year_id', 'status', 'confirmations',
        'total_revenue', 'total_expenses', 'net_result', 'closing_entry_id',
        'started_by', 'closed_by', 'closed_at', 'reversed_by', 'reversed_at', 'reversal_reason',
    ];

    protected function casts(): array
    {
        return [
            'confirmations' => 'array',
            'total_revenue' => 'decimal:2',
            'total_expenses' => 'decimal:2',
            'net_result' => 'decimal:2',
            'closed_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    public function fiscalYear()
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function closingEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'closing_entry_id');
    }

    public function startedBy()
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function reversedBy()
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    /** The attempt that is still live for a year — in progress or closed. */
    public static function currentFor(FiscalYear $year): ?self
    {
        return static::where('fiscal_year_id', $year->id)
            ->where('status', '!=', self::STATUS_REVERSED)
            ->latest('id')
            ->first();
    }

    public function isConfirmed(string $item): bool
    {
        return isset(($this->confirmations ?? [])[$item]);
    }
}
