<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class JournalEntry extends Model
{
    use Auditable, SoftDeletes;

    use BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'entry_number', 'entry_date', 'fiscal_year_id', 'accounting_period_id',
        'journal_type', 'reference_type', 'reference_id', 'description',
        'total_debit', 'total_credit', 'is_posted', 'is_system_generated',
        'is_reversed', 'reversed_entry_id', 'posted_by', 'posted_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'total_debit' => 'decimal:2',
            'total_credit' => 'decimal:2',
            'is_posted' => 'boolean',
            'is_system_generated' => 'boolean',
            'is_reversed' => 'boolean',
            'posted_at' => 'datetime',
        ];
    }

    public function lines()
    {
        return $this->hasMany(JournalEntryLine::class)->orderBy('line_number');
    }

    public function fiscalYear()
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function accountingPeriod()
    {
        return $this->belongsTo(AccountingPeriod::class);
    }

    public function postedBy()
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reversedEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'reversed_entry_id');
    }

    /** Unified entry number: JE-YYYY-NNNN everywhere (guide gotcha #7). */
    public static function generateEntryNumber(): string
    {
        $prefix = 'JE-' . now()->year . '-';
        $last = static::withTrashed()->where('entry_number', 'like', $prefix . '%')
            ->orderByDesc('entry_number')->first();
        $next = $last ? ((int) substr($last->entry_number, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    public function isBalanced(): bool
    {
        $debit = (string) $this->lines()->sum('debit');
        $credit = (string) $this->lines()->sum('credit');

        return abs((float) bcsub($debit, $credit, 2)) <= 0.01;
    }

    public function recalcTotals(): void
    {
        $this->total_debit = $this->lines()->sum('debit');
        $this->total_credit = $this->lines()->sum('credit');
        $this->save();
    }

    /**
     * The single posting chokepoint: atomic, validates balance, refuses
     * closed period / fiscal year, and updates cached account balances.
     */
    public function post(?int $userId = null): bool
    {
        if ($this->is_posted) {
            return false;
        }
        if (! $this->isBalanced()) {
            throw new RuntimeException('Cannot post: journal entry is not balanced (debits ≠ credits).');
        }
        if ($this->accountingPeriod && $this->accountingPeriod->is_closed) {
            throw new RuntimeException('Cannot post into a closed accounting period.');
        }
        if ($this->fiscalYear && $this->fiscalYear->is_closed) {
            throw new RuntimeException('Cannot post into a closed fiscal year.');
        }

        DB::transaction(function () use ($userId) {
            $this->recalcTotals();
            $this->is_posted = true;
            $this->posted_by = $userId ?? auth()->id();
            $this->posted_at = now();
            $this->save();

            foreach ($this->lines as $line) {
                $this->applyToBalance($line, 1);
            }
        });

        return true;
    }

    public function unpost(): bool
    {
        if (! $this->is_posted) {
            return false;
        }
        if ($this->accountingPeriod && $this->accountingPeriod->is_closed) {
            throw new RuntimeException('Cannot un-post in a closed accounting period.');
        }

        DB::transaction(function () {
            foreach ($this->lines as $line) {
                $this->applyToBalance($line, -1);
            }
            $this->is_posted = false;
            $this->posted_by = null;
            $this->posted_at = null;
            $this->save();
        });

        return true;
    }

    private function applyToBalance(JournalEntryLine $line, int $sign): void
    {
        $account = $line->account;
        if (! $account) {
            return;
        }
        $delta = $account->normal_balance === 'debit'
            ? bcsub((string) $line->debit, (string) $line->credit, 2)
            : bcsub((string) $line->credit, (string) $line->debit, 2);

        $account->current_balance = bcadd((string) $account->current_balance, bcmul($delta, (string) $sign, 2), 2);
        $account->save();
    }
}
