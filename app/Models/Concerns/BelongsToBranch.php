<?php

namespace App\Models\Concerns;

use App\Models\Branch;
use App\Models\Scopes\BranchScope;
use App\Support\BranchContext;

/**
 * Marks a model as branch-owned: it carries a branch_id, is auto-filtered by
 * the active branch (BranchScope), and auto-stamps branch_id on create.
 *
 * Models using this should include 'branch_id' in $fillable.
 */
trait BelongsToBranch
{
    public static function bootBelongsToBranch(): void
    {
        static::addGlobalScope(new BranchScope());

        static::creating(function ($model) {
            if (empty($model->branch_id) && BranchContext::isActive() && ! BranchContext::isAllBranches()) {
                $model->branch_id = BranchContext::current();
            }
        });
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function getBranchColumn(): string
    {
        return 'branch_id';
    }

    public function getQualifiedBranchColumn(): string
    {
        return $this->qualifyColumn($this->getBranchColumn());
    }
}
