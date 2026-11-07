<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\ClassSection;
use App\Models\TeacherSubjectAssignment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Which classes and subjects a teacher teaches, for the current year.
 *
 * Two sources, because schools organise it two ways:
 *   - the class teacher of a section (class_sections.class_teacher_id). At
 *     nursery and primary the class teacher teaches every subject of the
 *     class, so being class teacher is enough to mark all of them. At
 *     secondary the class master is responsible for the class — register,
 *     council, report cards — but marks only the subjects assigned to them.
 *   - explicit subject assignments (teacher_subject_assignments), one row per
 *     teacher × subject × section for the year.
 *
 * Head teachers, principals and admins are not restricted: restricted()
 * answers false for every role but 'teacher', and every other method then
 * answers "everything", so callers never branch on role themselves.
 */
class TeachingScope
{
    /** @var array<int, array{sections: array<int, int>, class_teacher_of: array<int, int>, subjects: array<int, array<int, int>>}> */
    private array $memo = [];

    public function restricted(?User $user): bool
    {
        // A teacher sees their own classes. A head teacher or administrator
        // who also teaches still sees the whole school.
        return $user !== null && $user->isTeacher() && ! $user->isAdmin();
    }

    /** @return array<int, int> class section ids the teacher works in */
    public function sectionIds(?User $user): array
    {
        return $this->load($user)['sections'];
    }

    /** @return array<int, int> sections the teacher is class teacher of */
    public function classTeacherOf(?User $user): array
    {
        return $this->load($user)['class_teacher_of'];
    }

    public function canUseSection(?User $user, int $sectionId): bool
    {
        return ! $this->restricted($user) || in_array($sectionId, $this->sectionIds($user), true);
    }

    /** May this user enter marks for this subject in this section? */
    public function canMark(?User $user, int $sectionId, int $subjectId): bool
    {
        if (! $this->restricted($user)) {
            return true;
        }

        return in_array($subjectId, $this->subjectIdsIn($user, $sectionId), true);
    }

    /**
     * Subjects the teacher may mark in a section: the assigned ones, plus
     * every subject of the class when they are its class teacher at nursery
     * or primary.
     *
     * @return array<int, int>
     */
    public function subjectIdsIn(?User $user, int $sectionId): array
    {
        $scope = $this->load($user);
        $ids = $scope['subjects'][$sectionId] ?? [];

        if (in_array($sectionId, $scope['class_teacher_of'], true)) {
            $section = ClassSection::with('form')->find($sectionId);
            if ($section && $section->form?->school_level === 'nursery_primary') {
                $ids = array_merge($ids, DB::table('form_subject')->where('form_id', $section->form_id)->pluck('subject_id')->map(fn ($v) => (int) $v)->all());
            }
        }

        return array_values(array_unique($ids));
    }

    /** Narrow a ClassSection query to the teacher's sections. */
    public function scopeSections($query, ?User $user)
    {
        return $this->restricted($user) ? $query->whereIn('class_sections.id', $this->sectionIds($user) ?: [0]) : $query;
    }

    /** Narrow a Form query to forms the teacher has a section in. */
    public function scopeForms($query, ?User $user)
    {
        if (! $this->restricted($user)) {
            return $query;
        }

        $formIds = ClassSection::whereIn('id', $this->sectionIds($user) ?: [0])->pluck('form_id')->unique()->all();

        return $query->whereIn('forms.id', $formIds ?: [0]);
    }

    /**
     * The teacher's sections with the subjects they teach in each, for the
     * dashboard and the "my classes" shortcuts.
     *
     * @return Collection<int, array{section: ClassSection, subjects: Collection, class_teacher: bool}>
     */
    public function myClasses(User $user): Collection
    {
        $scope = $this->load($user);
        $sections = ClassSection::with('form')->whereIn('id', $scope['sections'] ?: [0])->where('is_active', true)->get()
            ->sortBy(fn ($s) => [$s->form->display_order ?? 0, $s->name]);

        $subjects = \App\Models\Subject::query()->get()->keyBy('id');

        return $sections->map(fn ($section) => [
            'section' => $section,
            'class_teacher' => in_array($section->id, $scope['class_teacher_of'], true),
            'subjects' => collect($this->subjectIdsIn($user, $section->id))->map(fn ($id) => $subjects->get($id))->filter()->sortBy('name')->values(),
        ])->values();
    }

    public function forget(): void
    {
        $this->memo = [];
    }

    private function load(?User $user): array
    {
        if (! $user) {
            return ['sections' => [], 'class_teacher_of' => [], 'subjects' => []];
        }

        return $this->memo[$user->id] ??= $this->resolve($user);
    }

    private function resolve(User $user): array
    {
        $classTeacherOf = ClassSection::where('class_teacher_id', $user->id)->where('is_active', true)->pluck('id')->map(fn ($v) => (int) $v)->all();

        $sessionId = AcademicSession::current()?->id;
        $assignments = $sessionId
            ? TeacherSubjectAssignment::where('user_id', $user->id)->where('academic_session_id', $sessionId)->get(['class_section_id', 'subject_id'])
            : collect();

        $subjects = [];
        foreach ($assignments as $a) {
            $subjects[(int) $a->class_section_id][] = (int) $a->subject_id;
        }

        return [
            'sections' => array_values(array_unique(array_merge($classTeacherOf, array_keys($subjects)))),
            'class_teacher_of' => $classTeacherOf,
            'subjects' => $subjects,
        ];
    }
}
