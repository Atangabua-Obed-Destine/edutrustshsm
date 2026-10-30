<?php

namespace App\Services;

use App\Models\Guardian;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

/**
 * Who is a parent to whom.
 *
 * The pivot is the truth about which adults are linked to a child.
 * `students.guardian_id` is kept beside it meaning one narrower thing — the
 * primary guardian — because a good deal of the system reads it: fee
 * reminders, admissions, bulk upload, the student record. Keeping both in
 * step here means none of that had to change, and there is exactly one place
 * where they can fall out of step.
 */
class GuardianLinkService
{
    /**
     * Link an adult to a child, or update the link if there already is one.
     *
     * The first adult linked to a child becomes the primary by default: a
     * child with links but no primary would have nobody the school writes to.
     */
    public function link(
        Guardian $guardian,
        Student $student,
        ?string $relationship = null,
        ?bool $primary = null,
        bool $canAccessPortal = true
    ): void {
        DB::transaction(function () use ($guardian, $student, $relationship, $primary, $canAccessPortal) {
            $isFirst = $student->guardians()->count() === 0;
            $makePrimary = $primary ?? $isFirst;

            $student->guardians()->syncWithoutDetaching([
                $guardian->id => [
                    'branch_id' => $student->branch_id,
                    'relationship' => $relationship,
                    'can_access_portal' => $canAccessPortal,
                    // Set below, so there is only one path that decides this.
                    'is_primary' => false,
                ],
            ]);

            if ($relationship !== null) {
                $student->guardians()->updateExistingPivot($guardian->id, ['relationship' => $relationship]);
            }

            if ($makePrimary) {
                $this->makePrimary($guardian, $student);
            } else {
                $this->syncPrimaryColumn($student);
            }
        });
    }

    /**
     * Make this adult the child's primary contact, demoting whoever held it.
     */
    public function makePrimary(Guardian $guardian, Student $student): void
    {
        DB::transaction(function () use ($guardian, $student) {
            $student->guardians()->newPivotStatement()
                ->where('student_id', $student->id)
                ->update(['is_primary' => false]);

            $student->guardians()->updateExistingPivot($guardian->id, [
                'is_primary' => true,
                // The school's first point of contact must be able to see the
                // child, whatever the link said before.
                'can_access_portal' => true,
            ]);

            $this->syncPrimaryColumn($student);
        });
    }

    /**
     * Remove a link.
     *
     * A child is never left with no primary: if the one removed held it, the
     * oldest remaining link takes over. Removing the last link is refused —
     * a pupil with no adult attached has nobody to contact about them.
     */
    public function unlink(Guardian $guardian, Student $student): bool
    {
        if ($student->guardians()->count() <= 1) {
            return false;
        }

        DB::transaction(function () use ($guardian, $student) {
            $student->guardians()->detach($guardian->id);

            $hasPrimary = $student->guardians()->wherePivot('is_primary', true)->exists();

            if (! $hasPrimary) {
                $next = $student->guardians()->orderBy('guardian_student.id')->first();

                if ($next) {
                    $student->guardians()->updateExistingPivot($next->id, ['is_primary' => true]);
                }
            }

            $this->syncPrimaryColumn($student);
        });

        return true;
    }

    /** Change what an adult is to a child without touching anything else. */
    public function setRelationship(Guardian $guardian, Student $student, ?string $relationship): void
    {
        $student->guardians()->updateExistingPivot($guardian->id, ['relationship' => $relationship]);
    }

    /** Allow or withhold portal access for one link. */
    public function setPortalAccess(Guardian $guardian, Student $student, bool $allowed): bool
    {
        // The primary contact keeps access; withholding it would leave the
        // child's main parent unable to see them.
        if (! $allowed && $this->isPrimary($guardian, $student)) {
            return false;
        }

        $student->guardians()->updateExistingPivot($guardian->id, ['can_access_portal' => $allowed]);

        return true;
    }

    public function isPrimary(Guardian $guardian, Student $student): bool
    {
        return $student->guardians()
            ->wherePivot('is_primary', true)
            ->where('guardians.id', $guardian->id)
            ->exists();
    }

    /**
     * Point students.guardian_id at whoever currently holds the primary link.
     *
     * The one place the column and the pivot are reconciled.
     */
    public function syncPrimaryColumn(Student $student): void
    {
        $primary = $student->guardians()->wherePivot('is_primary', true)->first()
            ?? $student->guardians()->orderBy('guardian_student.id')->first();

        if ($primary && $student->guardian_id !== $primary->id) {
            $student->forceFill(['guardian_id' => $primary->id])->save();
        }
    }
}
