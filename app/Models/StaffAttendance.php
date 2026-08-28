<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * One staff member's attendance for one day.
 *
 * Note this does NOT affect pay — payroll is computed from basic_salary. The
 * register exists so HR can see who was in, not to dock anyone.
 */
class StaffAttendance extends Model
{
    use Auditable;

    /** Statuses that count as the person being at work. */
    public const PRESENT_STATUSES = ['present', 'late'];

    /** Statuses that are neither present nor a black mark. */
    public const NEUTRAL_STATUSES = ['leave', 'holiday'];

    protected $fillable = [
        'user_id', 'date', 'status', 'check_in', 'check_out', 'note', 'recorded_by',
    ];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function scopeForMonth($query, int $year, int $month)
    {
        return $query->whereYear('date', $year)->whereMonth('date', $month);
    }

    public function isPresent(): bool
    {
        return in_array($this->status, self::PRESENT_STATUSES, true);
    }
}
