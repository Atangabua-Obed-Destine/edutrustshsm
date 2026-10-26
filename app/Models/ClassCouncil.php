<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One class council sitting: a class, a term, and what was decided.
 *
 * While it is open, the figures on screen are live. Once concluded they are
 * frozen onto the record, because the minutes have to say what the meeting
 * saw rather than what the database says today.
 */
class ClassCouncil extends Model
{
    use Auditable, BelongsToBranch;

    public const OPEN = 'open';
    public const CONCLUDED = 'concluded';

    protected $fillable = [
        'branch_id', 'class_section_id', 'term_id', 'academic_session_id',
        'reference', 'meeting_date', 'venue', 'chaired_by', 'secretary_id',
        'status', 'deliberations', 'action_items',
        'students', 'passed', 'failed', 'class_average', 'pass_rate',
        'highest_average', 'lowest_average',
        'concluded_at', 'concluded_by', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'meeting_date' => 'date',
            'concluded_at' => 'datetime',
            'class_average' => 'decimal:2',
            'pass_rate' => 'decimal:2',
            'highest_average' => 'decimal:2',
            'lowest_average' => 'decimal:2',
        ];
    }

    public function classSection()
    {
        return $this->belongsTo(ClassSection::class);
    }

    public function term()
    {
        return $this->belongsTo(Term::class);
    }

    public function academicSession()
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function entries()
    {
        return $this->hasMany(ClassCouncilEntry::class);
    }

    public function signatures()
    {
        return $this->hasMany(ClassCouncilSignature::class);
    }

    public function chair()
    {
        return $this->belongsTo(User::class, 'chaired_by');
    }

    public function secretary()
    {
        return $this->belongsTo(User::class, 'secretary_id');
    }

    public function concludedBy()
    {
        return $this->belongsTo(User::class, 'concluded_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::OPEN);
    }

    public function isOpen(): bool
    {
        return $this->status === self::OPEN;
    }

    public function isConcluded(): bool
    {
        return $this->status === self::CONCLUDED;
    }

    public function statusLabel(): string
    {
        return $this->isConcluded() ? __('Concluded') : __('Open');
    }

    /**
     * The next reference in this branch's run — CC-2026-0001.
     *
     * Read without the branch scope so two branches cannot be handed the same
     * number by a query running in All-Branches mode.
     */
    public static function nextReference(?int $branchId): string
    {
        $prefix = 'CC-'.now()->year.'-';

        $last = static::withoutBranchScope()
            ->where('branch_id', $branchId)
            ->where('reference', 'like', $prefix.'%')
            ->orderByDesc('reference')
            ->value('reference');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
