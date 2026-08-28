<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DepreciationSchedule extends Model
{
    protected $fillable = [
        'fixed_asset_id', 'period_number', 'period_date',
        'amount', 'accumulated', 'book_value', 'is_posted', 'journal_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'period_date' => 'date',
            'amount' => 'decimal:2',
            'accumulated' => 'decimal:2',
            'book_value' => 'decimal:2',
            'is_posted' => 'boolean',
        ];
    }

    public function asset()
    {
        return $this->belongsTo(FixedAsset::class, 'fixed_asset_id');
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function scopeUnposted($query)
    {
        return $query->where('is_posted', false);
    }

    /** Periods that have come due but are not yet in the ledger. */
    public function scopeDue($query, ?string $asOf = null)
    {
        return $query->unposted()->whereDate('period_date', '<=', $asOf ?: now()->toDateString());
    }
}
