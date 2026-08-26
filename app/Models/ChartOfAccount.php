<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChartOfAccount extends Model
{
    use SoftDeletes;

    protected $table = 'chart_of_accounts';

    use BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'account_code', 'account_name', 'account_name_fr', 'parent_id',
        'class_number', 'account_type', 'account_category', 'normal_balance',
        'opening_balance', 'current_balance', 'is_active', 'is_system',
        'display_order', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'current_balance' => 'decimal:2',
            'is_active' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    public function parent()
    {
        return $this->belongsTo(ChartOfAccount::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(ChartOfAccount::class, 'parent_id');
    }

    public function lines()
    {
        return $this->hasMany(JournalEntryLine::class, 'account_id');
    }

    /** Only detail + active accounts can be posted to. */
    public function canPost(): bool
    {
        return $this->account_category === 'detail' && $this->is_active;
    }

    public function scopePostable($query)
    {
        return $query->where('account_category', 'detail')->where('is_active', true);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Balance for this account computed from POSTED journal lines (source of truth),
     * signed by normal balance. Optional date bounds.
     */
    public function postedBalance(?string $from = null, ?string $to = null): string
    {
        $q = JournalEntryLine::where('account_id', $this->id)
            ->whereHas('journalEntry', function ($je) use ($from, $to) {
                $je->where('is_posted', true);
                if ($from) $je->whereDate('entry_date', '>=', $from);
                if ($to) $je->whereDate('entry_date', '<=', $to);
            });

        $debit = (string) $q->sum('debit');
        $credit = (string) $q->sum('credit');

        return $this->normal_balance === 'debit'
            ? bcsub($debit, $credit, 2)
            : bcsub($credit, $debit, 2);
    }
}
