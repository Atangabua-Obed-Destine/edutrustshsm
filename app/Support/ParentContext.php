<?php

namespace App\Support;

use App\Models\Student;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Resolves the parent portal's currently-active child (student) selection,
 * stored in the session. A guardian may have several children; the portal
 * shows one child's data at a time, switchable from the dashboard.
 *
 * Mirrors BranchContext/LevelContext but scoped to the `guardians` auth guard.
 */
class ParentContext
{
    public const SESSION_KEY = 'parent_active_student_id';

    /** The authenticated guardian, or null if not on the parent guard. */
    public static function guardian()
    {
        return Auth::guard('guardians')->user();
    }

    /**
     * All children belonging to the authenticated guardian, eager-loaded with
     * their current enrollment context for card rendering.
     *
     * @return Collection<int, Student>
     */
    public static function children(): Collection
    {
        $guardian = self::guardian();
        if (! $guardian) {
            return new Collection();
        }

        return Student::where('guardian_id', $guardian->id)
            ->orderBy('first_name')
            ->get();
    }

    /** The id of the active child, falling back to the first available child. */
    public static function currentId(): ?int
    {
        $children = self::children();
        if ($children->isEmpty()) {
            return null;
        }

        $sessionId = session(self::SESSION_KEY);
        if ($sessionId && $children->contains('id', $sessionId)) {
            return (int) $sessionId;
        }

        // Default to the first child and persist it.
        $first = $children->first()->id;
        session([self::SESSION_KEY => $first]);

        return $first;
    }

    /** The active child model, or null if the guardian has no children. */
    public static function currentStudent(): ?Student
    {
        $id = self::currentId();

        return $id ? self::children()->firstWhere('id', $id) : null;
    }

    /** Persist a child selection, but only if it belongs to this guardian. */
    public static function set(int $studentId): bool
    {
        if (self::children()->contains('id', $studentId)) {
            session([self::SESSION_KEY => $studentId]);

            return true;
        }

        return false;
    }

    /**
     * Resolve a requested student id and assert it belongs to the authenticated
     * guardian. Aborts with 403 on any mismatch — the central security gate for
     * every child-specific portal page.
     */
    public static function authorizeStudent(int $studentId): Student
    {
        $guardian = self::guardian();
        $student = Student::where('guardian_id', $guardian?->id)
            ->where('id', $studentId)
            ->first();

        abort_if(! $student, 403, __('You do not have access to this student.'));

        // Keep the active selection in sync with the page being viewed.
        session([self::SESSION_KEY => $student->id]);

        return $student;
    }

    /** Whether the guardian has more than one child (controls the switcher UI). */
    public static function hasMultipleChildren(): bool
    {
        return self::children()->count() > 1;
    }
}
