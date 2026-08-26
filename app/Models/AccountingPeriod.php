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

    /** Find the open period containing a given date (auto-posting targets this). */
    public static function forDate(string $date): ?self
    {
        return static::where('is_closed', false)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->first();
    }
}
