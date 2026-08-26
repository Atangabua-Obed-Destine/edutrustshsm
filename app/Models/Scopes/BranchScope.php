<?php

namespace App\Models\Scopes;

use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope that confines every query on a branch-owned model to the
 * branch(es) the current request is allowed to see:
 *
 *  - single-branch mode  -> WHERE branch_id = active branch
 *  - "All Branches" mode  -> WHERE branch_id IN (branches the user may access)
 *
 * Bypass entirely with Model::withoutBranchScope() for cross-branch admin tasks.
 */
class BranchScope implements Scope
{
    /**
     * Re-entrancy guard. Resolving the active branch context can itself query a
     * branch-owned model — most notably the Guardian model, which backs the
     * `guardians` auth guard: auth()->check() triggers the user provider, which
     * queries `guardians`, which re-enters this scope. Without this flag that
     * recurses until memory is exhausted. While resolving context we skip scoping
     * (the auth lookup is by primary key / credentials, so it needs no branch filter).
     */
    protected static bool $resolving = false;

    public function apply(Builder $builder, Model $model): void
    {
        if (static::$resolving) {
            return;
        }

        static::$resolving = true;

        try {
            // Outside an HTTP/auth context (console, migrations, seeders) do not scope.
            if (! BranchContext::isActive()) {
                return;
            }

            $column = $model->getQualifiedBranchColumn();

            if (BranchContext::isAllBranches()) {
                $builder->whereIn($column, BranchContext::accessibleIds());
                return;
            }

            $builder->where($column, BranchContext::current());
        } finally {
            static::$resolving = false;
        }
    }

    public function extend(Builder $builder): void
    {
        $builder->macro('withoutBranchScope', function (Builder $builder) {
            return $builder->withoutGlobalScope(static::class);
        });
    }
}
