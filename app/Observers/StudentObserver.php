<?php

namespace App\Observers;

use App\Models\Student;
use App\Services\GuardianLinkService;

/**
 * Keeps the guardian pivot in step with students.guardian_id.
 *
 * Students are created from three places — the registration screen, admissions
 * and bulk upload — and all three set guardian_id directly. Rather than
 * change each of them, and risk a fourth appearing later that forgets, the
 * link is made here wherever a student comes from.
 */
class StudentObserver
{
    public function __construct(private GuardianLinkService $links) {}

    public function created(Student $student): void
    {
        $this->ensureLinked($student);
    }

    /**
     * A student moved to a different primary guardian keeps the old link.
     *
     * Reassigning the school's first point of contact is not the same as
     * saying the previous adult is no longer the child's parent, and
     * guessing wrong would quietly cut someone off from their child.
     */
    public function updated(Student $student): void
    {
        if ($student->wasChanged('guardian_id')) {
            $this->ensureLinked($student);
        }
    }

    private function ensureLinked(Student $student): void
    {
        if (! $student->guardian_id || ! $student->guardian) {
            return;
        }

        $this->links->link($student->guardian, $student, primary: true);
    }
}
