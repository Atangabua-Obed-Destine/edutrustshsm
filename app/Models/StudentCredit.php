<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * Money held on a student's behalf, from paying more than they owed.
 *
 * A credit is a liability until it is applied to a fee: the school has the cash
 * but has not earned it. Applying a credit moves it from that liability into
 * revenue without any second cash movement.
 */
class StudentCredit extends Model
{
    use Auditable;

    protected $fillable = [
        'student_enrollment_id', 'payment_id', 'amount', 'used_amount', 'refunded_amount',
        'balance', 'source', 'note', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'used_amount' => 'decimal:2',
            'refunded_amount' => 'decimal:2',
            'balance' => 'decimal:2',
        ];
    }

    public function enrollment()
    {
        return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id');
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function refunds()
    {
        return $this->hasMany(StudentCreditRefund::class)->orderByDesc('id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeAvailable($query)
    {
        return $query->where('balance', '>', 0);
    }

    /** Credit still available to an enrollment. */
    public static function availableFor(int $enrollmentId): float
    {
        return (float) static::where('student_enrollment_id', $enrollmentId)
            ->where('balance', '>', 0)
            ->sum('balance');
    }

    /**
     * What is left: the original amount, less what went to fees and what was
     * paid back. Kept in one place because three different writers used to
     * work it out, and a refund would have been invisible to all of them.
     */
    public function recomputeBalance(): static
    {
        $this->balance = max(0, round(
            (float) $this->amount - (float) $this->used_amount - (float) $this->refunded_amount,
            2
        ));

        return $this;
    }

    /** Draw an amount down from this credit. Returns what was actually drawn. */
    public function draw(float $amount): float
    {
        $drawn = min($amount, (float) $this->balance);

        if ($drawn <= 0) {
            return 0.0;
        }

        $this->used_amount = (float) $this->used_amount + $drawn;
        $this->recomputeBalance()->save();

        return $drawn;
    }
}
