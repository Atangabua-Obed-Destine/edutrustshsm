<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Routing\Controllers\Middleware;

/**
 * Per-action permission gating for a controller.
 *
 * The reference system expresses this as `$this->middleware('permission:...')`
 * calls in the constructor, keyed off a `$access` property. That method was
 * removed in Laravel 11, so the same convention is expressed here through the
 * HasMiddleware interface instead.
 *
 * A controller declares its permission prefix and, if it has verbs beyond the
 * CRUD four, adds them:
 *
 *     class IncomeController extends Controller implements HasMiddleware
 *     {
 *         use AuthorizesModule;
 *
 *         protected static string $access = 'income';
 *     }
 *
 * Permission names match the seeded catalogue exactly: "<access>.<verb>".
 */
trait AuthorizesModule
{
    public static function middleware(): array
    {
        $extra = static::extraMiddleware();

        // An action a controller gates with its own verb is decided by that
        // verb alone. Stacking the generic CRUD rule on top demanded
        // permissions that do not exist: saving a register needed
        // "attendance.create" as well as "attendance.mark", recording a
        // payment "payment.create" as well as "fee-collection.collect", so
        // only the super admin could do either.
        $covered = collect($extra)
            ->filter(fn ($m) => $m instanceof Middleware && $m->only !== null)
            ->flatMap(fn (Middleware $m) => (array) $m->only)
            ->unique()
            ->all();

        $crud = collect(static::crudMiddleware())
            ->map(function (Middleware $m) use ($covered) {
                if ($m->only === null) {
                    return $m;
                }
                $left = array_values(array_diff((array) $m->only, $covered));

                return $left === [] ? null : new Middleware($m->middleware, only: $left);
            })
            ->filter()
            ->values()
            ->all();

        return array_merge($crud, $extra);
    }

    /**
     * The standard CRUD gating.
     *
     * Reading a module is allowed by ANY of its four core permissions, matching
     * the reference's `permission:x-view|x-create|x-edit|x-delete` on index/show
     * — someone who may edit can necessarily see the list they edit from.
     *
     * @return array<int, Middleware>
     */
    protected static function crudMiddleware(): array
    {
        $a = static::$access;

        return [
            new Middleware(
                "permission:{$a}.view|{$a}.create|{$a}.edit|{$a}.delete",
                only: ['index', 'show']
            ),
            new Middleware("permission:{$a}.create", only: ['create', 'store']),
            new Middleware("permission:{$a}.edit", only: ['edit', 'update']),
            new Middleware("permission:{$a}.delete", only: ['destroy']),
        ];
    }

    /**
     * Gating for verbs beyond CRUD. Override per controller.
     *
     * @return array<int, Middleware>
     */
    protected static function extraMiddleware(): array
    {
        return [];
    }

    /** Convenience for building an extra rule. */
    protected static function can(string $permission, array $methods): Middleware
    {
        return new Middleware('permission:'.$permission, only: $methods);
    }
}
