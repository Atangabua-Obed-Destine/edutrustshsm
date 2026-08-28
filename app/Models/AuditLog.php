<?php

namespace App\Models;

use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    // NOTE: deliberately NOT Auditable — auditing the audit log would recurse.
    //
    // It also does NOT use BelongsToBranch. That trait's global scope filters on
    // `branch_id = <current>`, and entries written outside an HTTP auth context
    // (console commands, seeders, anything before login) carry a NULL branch —
    // which would make them permanently invisible. An audit trail that silently
    // hides entries is worse than none, so branch filtering here is explicit
    // (see scopeVisible) and always keeps system-level entries.

    public $timestamps = false;

    protected $fillable = [
        'branch_id', 'user_id', 'action', 'model_type', 'model_id',
        'old_values', 'new_values', 'ip_address', 'user_agent', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Entries the current user may see: their branch's, plus system-level
     * entries that were written without a branch context.
     */
    public function scopeVisible(Builder $query): Builder
    {
        if (! BranchContext::isActive()) {
            return $query;
        }

        $branchIds = BranchContext::isAllBranches()
            ? BranchContext::accessibleIds()
            : [BranchContext::current()];

        return $query->where(function (Builder $q) use ($branchIds) {
            $q->whereIn('branch_id', $branchIds)->orWhereNull('branch_id');
        });
    }

    public static function log(string $action, ?string $modelType = null, ?int $modelId = null, ?array $old = null, ?array $new = null): self
    {
        return static::create([
            // Stamp the branch explicitly; there is no global scope doing it.
            'branch_id' => BranchContext::isActive() && ! BranchContext::isAllBranches()
                ? (BranchContext::current() ?: null)
                : null,
            'user_id' => auth()->id(),
            'action' => $action,
            'model_type' => $modelType,
            'model_id' => $modelId,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }
}
