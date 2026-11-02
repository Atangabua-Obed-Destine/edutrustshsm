<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\ClassSection;
use App\Models\TeacherSubjectAssignment;
use App\Models\User;
use App\Services\TeachingScope;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

/**
 * Who teaches what, this year: one screen, every class down the page, the
 * class teacher and a teacher per subject.
 *
 * It is what decides what a teacher sees when they log in. A teacher with
 * no class and no subject here sees nothing to mark, so the page also says
 * which classes still have subjects nobody teaches.
 */
class TeachingAssignmentController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'class-section';

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('class-section.view', ['index']),
            static::can('class-section.edit', ['update']),
        ];
    }

    public function index(Request $request)
    {
        $session = AcademicSession::current();

        $sections = ClassSection::with(['form', 'classTeacher'])
            ->where('is_active', true)
            ->whereHas('form', fn ($q) => $q->forCurrentLevel()->where('is_active', true))
            ->get()
            ->sortBy(fn ($s) => [$s->form->display_order, $s->name])
            ->values();

        $subjectsByForm = DB::table('form_subject')
            ->join('subjects', 'subjects.id', '=', 'form_subject.subject_id')
            ->whereIn('form_subject.form_id', $sections->pluck('form_id')->unique())
            ->where('subjects.is_active', true)
            ->orderBy('subjects.name')
            ->get(['form_subject.form_id', 'subjects.id', 'subjects.name', 'subjects.code'])
            ->groupBy('form_id');

        $assigned = $session
            ? TeacherSubjectAssignment::where('academic_session_id', $session->id)->get()
                ->groupBy('class_section_id')->map(fn ($rows) => $rows->pluck('user_id', 'subject_id'))
            : collect();

        $teachers = User::where('is_active', true)->whereIn('role', ['teacher', 'admin', 'super_admin'])
            ->orderBy('last_name')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'role']);

        // A class with a subject nobody teaches, at secondary, or with no
        // class teacher at primary: someone has to type those marks.
        $gaps = $sections->filter(function ($s) use ($subjectsByForm, $assigned) {
            if ($s->form->school_level === 'nursery_primary') {
                return ! $s->class_teacher_id;
            }
            $subjects = $subjectsByForm->get($s->form_id, collect());

            return $subjects->contains(fn ($subj) => ! ($assigned->get($s->id)?->get($subj->id)));
        })->count();

        return view('admin.teaching-assignments.index', [
            'session' => $session,
            'sections' => $sections,
            'subjectsByForm' => $subjectsByForm,
            'assigned' => $assigned,
            'teachers' => $teachers,
            'gaps' => $gaps,
            'open' => $request->integer('section') ?: null,
        ]);
    }

    public function update(Request $request, ClassSection $classSection, TeachingScope $scope)
    {
        $session = AcademicSession::current();
        abort_unless($session, 422, __('No current academic year is set.'));

        $data = $request->validate([
            'class_teacher_id' => ['nullable', 'exists:users,id'],
            'subjects' => ['nullable', 'array'],
            'subjects.*' => ['nullable', 'exists:users,id'],
        ]);

        $formSubjects = DB::table('form_subject')->where('form_id', $classSection->form_id)->pluck('subject_id')->map(fn ($v) => (int) $v)->flip();

        DB::transaction(function () use ($classSection, $session, $data, $formSubjects) {
            $classSection->update(['class_teacher_id' => $data['class_teacher_id'] ?? null]);

            foreach ((array) ($data['subjects'] ?? []) as $subjectId => $userId) {
                if (! $formSubjects->has((int) $subjectId)) {
                    continue;
                }

                $match = ['academic_session_id' => $session->id, 'class_section_id' => $classSection->id, 'subject_id' => (int) $subjectId];

                if ($userId) {
                    TeacherSubjectAssignment::updateOrCreate($match, ['user_id' => (int) $userId, 'branch_id' => $classSection->branch_id]);
                } else {
                    TeacherSubjectAssignment::where($match)->delete();
                }
            }
        });

        $scope->forget();

        return redirect()->route('admin.teaching-assignments.index', ['section' => $classSection->id])
            ->with('success', __(':class saved.', ['class' => $classSection->name]));
    }
}
