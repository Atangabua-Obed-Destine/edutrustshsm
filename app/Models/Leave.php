<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A staff leave request.
 *
 * Balances are derived, never stored: the days a staff member has used is
 * always recomputed from their leave rows, so there is no counter to drift.
 */
class Leave extends Model
{
    use Auditable;

    /** Requests that consume a staff member's allowance. */
    public const CONSUMING_STATUSES = ['pending', 'approved'];

    protected $fillable = [
        'user_id', 'leave_type_id', 'apply_date', 'from_date', 'to_date',
        'reason', 'attachment', 'pay_type', 'status',
        'reviewed_by', 'reviewed_at', 'review_notes',
    ];

    protected function casts(): array
    {
        return [
            'apply_date' => 'date',
            'from_date' => 'date',
            'to_date' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /** Leave overlapping a calendar year, by its start date. */
    public function scopeForYear(Builder $query, int $year): Builder
    {
        return $query->whereYear('from_date', $year);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /** Days requested, inclusive of both end dates. */
    public function daysCount(): int
    {
        if (! $this->from_date || ! $this->to_date) {
            return 0;
        }

        return (int) $this->from_date->diffInDays($this->to_date) + 1;
    }

    /**
     * Days of a given type this staff member has already committed in a year.
     *
     * Counts approved AND pending, so several stacked requests cannot slip past
     * the cap together. $excludeId lets an edit exclude the row being changed.
     *
     * The reference implementation of this omitted the user filter, so it summed
     * every staff member's leave — do not reintroduce that.
     */
    public static function usedDaysForType(int $userId, int $leaveTypeId, int $year, ?int $excludeId = null): int
    {
        return static::query()
            ->where('user_id', $userId)
            ->where('leave_type_id', $leaveTypeId)
            ->whereIn('status', self::CONSUMING_STATUSES)
            ->forYear($year)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->get()
            ->sum(fn (Leave $leave) => $leave->daysCount());
    }

    /**
     * Days still available to a staff member for a type this year.
     * Null means the type is uncapped.
     */
    public static function remainingForType(LeaveType $type, int $userId, int $year, ?int $excludeId = null): ?int
    {
        if (! $type->isCapped()) {
            return null;
        }

        $used = self::usedDaysForType($userId, $type->id, $year, $excludeId);

        return max(0, $type->annual_limit - $used);
    }
}
