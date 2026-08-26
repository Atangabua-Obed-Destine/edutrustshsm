<?php

namespace App\Support;

use App\Models\Branch;

/**
 * Resolves the admin's currently-active branch (campus) working context,
 * stored in the session and used by BranchScope to isolate data per branch.
 *
 * Mirrors LevelContext, one tenant level up. "All Branches" is a consolidated
 * mode (super_admin / owners) that widens queries to every accessible branch.
 */
class BranchContext
{
    public const SESSION_KEY = 'active_branch_id';
    public const ALL = 'all';

    /**
     * Whether we have an authenticated user to scope by. Off during console,
     * migrations and seeders so the global scope is a no-op there.
     */
    public static function isActive(): bool
    {
        return auth()->check() || auth('guardians')->check();
    }

    /**
     * Branch ids the current user may access. super_admin sees every branch;
     * everyone else sees the branches they're attached to via branch_user.
     *
     * @return array<int, int>
     */
    public static function accessibleIds(): array
    {
        // Parent/guardian portal: a guardian is pinned to their own branch only.
        if (auth('guardians')->check()) {
            $branchId = auth('guardians')->user()->branch_id;

            return $branchId ? [(int) $branchId] : [];
        }

        $user = auth()->user();
        if (! $user) {
            return [];
        }

        if (($user->role ?? null) === 'super_admin') {
            return Branch::where('is_active', true)->pluck('id')->all();
        }

        return $user->branches()->pluck('branches.id')->all();
    }

    /**
     * Whether the user is in consolidated "All Branches" mode.
     */
    public static function isAllBranches(): bool
    {
        return session(self::SESSION_KEY) === self::ALL && count(self::accessibleIds()) > 1;
    }

    /**
     * The active branch id (single-branch mode). Falls back to the user's
     * default/first accessible branch. Returns 0 when none — callers should
     * treat that as "no branch context yet".
     */
    public static function current(): int
    {
        $accessible = self::accessibleIds();

        if (empty($accessible)) {
            return 0;
        }

        // Parent/guardian portal: pinned to their single branch (Guardian has no
        // branches() pivot relation, so never fall through to the User-only logic).
        if (auth('guardians')->check()) {
            return (int) $accessible[0];
        }

        $session = session(self::SESSION_KEY);
        if ($session && $session !== self::ALL && in_array((int) $session, $accessible, true)) {
            return (int) $session;
        }

        // Prefer the user's default branch, else the first accessible one.
        $default = auth()->user()?->branches()
            ->wherePivot('is_default', true)
            ->whereIn('branches.id', $accessible)
            ->value('branches.id');

        return (int) ($default ?? $accessible[0]);
    }

    /**
     * Set the active branch ('all' for consolidated mode, or a branch id).
     */
    public static function set(string|int $value): void
    {
        if ($value === self::ALL) {
            session([self::SESSION_KEY => self::ALL]);
            return;
        }

        if (in_array((int) $value, self::accessibleIds(), true)) {
            session([self::SESSION_KEY => (int) $value]);
        }
    }

    /**
     * Branches the switcher should offer.
     *
     * @return \Illuminate\Support\Collection<int, Branch>
     */
    public static function availableBranches()
    {
        return Branch::whereIn('id', self::accessibleIds())->orderBy('name')->get();
    }

    /**
     * Whether to render the branch switcher (more than one branch available).
     */
    public static function showSwitcher(): bool
    {
        return count(self::accessibleIds()) > 1;
    }

    /**
     * The active Branch model (or null in All-Branches / no-context mode).
     */
    public static function currentBranch(): ?Branch
    {
        if (self::isAllBranches()) {
            return null;
        }
        $id = self::current();

        return $id ? Branch::find($id) : null;
    }
}
