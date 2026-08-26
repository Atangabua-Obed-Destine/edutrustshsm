<?php

namespace App\Models;

use Carbon\Carbon;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class FiscalYear extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'name', 'start_date', 'end_date', 'is_active', 'is_closed', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
            'is_closed' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Enforce exactly one active fiscal year.
        static::saving(function (FiscalYear $fy) {
            if ($fy->is_active) {
                static::where('id', '!=', $fy->id ?? 0)->where('is_active', true)->update(['is_active' => false]);
            }
        });
    }

    public function periods()
    {
        return $this->hasMany(AccountingPeriod::class)->orderBy('period_number');
    }

    public function journalEntries()
    {
        return $this->hasMany(JournalEntry::class);
    }

    public static function active(): ?self
    {
        return static::where('is_active', true)->first();
    }

    /** Create monthly accounting periods spanning the year. */
    public function generatePeriods(): void
    {
        if ($this->periods()->exists()) {
            return;
        }

        $cursor = Carbon::parse($this->start_date)->startOfMonth();
        $end = Carbon::parse($this->end_date)->startOfMonth();
        $n = 1;

        while ($cursor <= $end) {
            $this->periods()->create([
                'period_number' => $n,
                'name' => $cursor->format('F Y'),
                'start_date' => $cursor->copy()->startOfMonth()->toDateString(),
                'end_date' => $cursor->copy()->endOfMonth()->toDateString(),
            ]);
            $cursor->addMonth();
            $n++;
        }
    }

    public function canClose(): bool
    {
        return ! $this->periods()->where('is_closed', false)->exists()
            && ! $this->journalEntries()->where('is_posted', false)->exists();
    }
}
