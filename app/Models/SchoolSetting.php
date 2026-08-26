<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Model;

class SchoolSetting extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'school_name', 'school_short_name', 'school_code', 'address',
        'city', 'region', 'po_box', 'phone', 'email', 'website',
        'logo', 'motto', 'school_level_mode', 'currency', 'student_id_prefix', 'receipt_prefix',
        'max_terms_per_session', 'max_sequences_per_term', 'pass_mark',
        'promotion_threshold', 'min_attendance_percent', 'max_mark',
    ];

    protected function casts(): array
    {
        return [
            'pass_mark' => 'decimal:1',
            'promotion_threshold' => 'decimal:1',
            'max_mark' => 'decimal:1',
        ];
    }

    /**
     * The settings row for the active branch. In All-Branches / no-context mode
     * (e.g. console, or owner consolidated view) falls back to any first row so
     * branding helpers still resolve.
     */
    public static function current(): ?self
    {
        if (BranchContext::isActive() && ! BranchContext::isAllBranches()) {
            $branchId = BranchContext::current();
            if ($branchId) {
                return static::withoutBranchScope()->where('branch_id', $branchId)->first()
                    ?? static::withoutBranchScope()->first();
            }
        }

        return static::withoutBranchScope()->first();
    }

    /** Whether the school-level mode has been chosen for this installation. */
    public function isLevelModeConfigured(): bool
    {
        return ! empty($this->school_level_mode);
    }

    /**
     * The school_level keys this installation operates, derived from the mode.
     * Falls back to both levels when the mode is unset.
     *
     * @return array<int, string>
     */
    public function activeSchoolLevels(): array
    {
        return match ($this->school_level_mode) {
            'nursery_primary' => ['nursery_primary'],
            'secondary' => ['secondary'],
            default => ['nursery_primary', 'secondary'], // 'both' or unset
        };
    }
}
