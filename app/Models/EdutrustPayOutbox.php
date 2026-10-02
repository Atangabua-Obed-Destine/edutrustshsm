<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One queued or delivered push to EdutrustPay.
 *
 * Deliberately NOT BelongsToBranch. That trait's global scope filters on the
 * active branch, which is inactive in the console — where every one of these
 * rows is created and delivered. Branch is carried explicitly instead, and every
 * query names it.
 */
class EdutrustPayOutbox extends Model
{
    protected $table = 'edutrustpay_outbox';

    public const PENDING = 'pending';

    public const DELIVERED = 'delivered';

    public const FAILED = 'failed';

    protected $fillable = [
        'branch_id', 'kind', 'period', 'sequence', 'payload', 'payload_hash',
        'status', 'attempts', 'next_attempt_at', 'delivered_at',
        'last_status_code', 'last_response',
    ];

    protected function casts(): array
    {
        return [
            'next_attempt_at' => 'datetime',
            'delivered_at' => 'datetime',
            'attempts' => 'integer',
            'sequence' => 'integer',
            'branch_id' => 'integer',
        ];
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function scopeDue(Builder $query): Builder
    {
        return $query->where('status', self::PENDING)
            ->where(fn ($q) => $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
            ->orderBy('id');
    }

    public function isDelivered(): bool
    {
        return $this->status === self::DELIVERED;
    }
}
