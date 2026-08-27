<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class AccountingPeriod extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'fiscal_year_id', 'period_number', 'name', 'start_date', 'end_date', 'is_closed',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_closed' => 'boolean',
        ];
    }

    public function fiscalYear()
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function journalEntries()
    {
        return $this->hasMany(JournalEntry::class);
    }

    /**
     * Find the period containing a given date, OPEN OR CLOSED.
     *
     * This deliberately does not filter on is_closed. It used to, which meant a
     * date inside a closed period resolved to null — so the entry was created
     * with accounting_period_id = NULL and JournalEntry::post()'s closed-period
     * guard (`if ($this->accountingPeriod && ...->is_closed)`) never fired.
     * Back-dated postings slipped into closed periods, unattributed. Returning
     * the real period lets post() refuse it properly.
     */
    public static function forDate(string $date): ?self
    {
        return static::whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->first();
    }
}
