<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecurringJournalEntryLine extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'recurring_journal_entry_id', 'account_id', 'line_number',
        'debit', 'credit', 'description',
    ];

    protected function casts(): array
    {
        return ['debit' => 'decimal:2', 'credit' => 'decimal:2'];
    }

    public function account()
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }

    public function template()
    {
        return $this->belongsTo(RecurringJournalEntry::class, 'recurring_journal_entry_id');
    }
}
