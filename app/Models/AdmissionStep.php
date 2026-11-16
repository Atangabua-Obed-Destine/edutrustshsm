<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

/** One step of an application signed off, waived or sent back. Never edited. */
class AdmissionStep extends Model
{
    use BelongsToBranch;

    public const DONE = 'done';
    public const WAIVED = 'waived';
    public const RETURNED = 'returned';

    protected $fillable = [
        'branch_id', 'admission_application_id', 'step', 'outcome', 'note', 'score', 'decided_by', 'decided_at',
    ];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime', 'score' => 'decimal:2'];
    }

    public function application()
    {
        return $this->belongsTo(AdmissionApplication::class, 'admission_application_id');
    }

    public function decidedBy()
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function outcomeLabel(): string
    {
        return match ($this->outcome) {
            self::DONE => __('Done'),
            self::WAIVED => __('Not required'),
            self::RETURNED => __('Sent back'),
            default => $this->outcome,
        };
    }
}
