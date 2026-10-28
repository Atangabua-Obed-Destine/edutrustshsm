<?php

namespace App\Services;

use App\Models\Form;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Support\LevelContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pupils and classes that belong to a level this school no longer runs.
 *
 * Every list in the portal is filtered to the school's level mode. That is
 * right — a primary school should not be offered Form 5 — but it means a
 * school that switches mode with pupils still enrolled at the old level
 * silently loses them. The Student List reads as empty, the dashboard counts
 * zero, and nothing on any screen says why.
 *
 * These records are not an error: a school that has genuinely stopped
 * running secondary still has the last cohort's files. What they need is to
 * be named, counted, and given a proper way out — graduated or transferred
 * through the ordinary exit flow — rather than hidden.
 */
class OutOfScopeService
{
    /**
     * Remembered for the request: the banner and the menu both ask, on every
     * screen. The service is bound as a singleton so they share the answer.
     */
    private ?array $summary = null;

    public function __construct(private StudentExitService $exits) {}

    /**
     * Levels present in the data that the school does not run.
     *
     * @return array<int, string>
     */
    public function levels(): array
    {
        if (! LevelContext::isConfigured()) {
            return [];
        }

        return array_values(array_diff(array_keys(LevelContext::labels()), LevelContext::options()));
    }

    /**
     * How much is hidden: active classes and currently-enrolled pupils at a
     * level the school does not run.
     *
     * @return array{levels: array<int, string>, classes: int, pupils: int}
     */
    public function summary(): array
    {
        if ($this->summary !== null) {
            return $this->summary;
        }

        $levels = $this->levels();

        if ($levels === []) {
            return $this->summary = ['levels' => [], 'classes' => 0, 'pupils' => 0];
        }

        return $this->summary = [
            'levels' => $levels,
            'classes' => Form::active()->whereIn('school_level', $levels)->count(),
            'pupils' => $this->pupilQuery($levels)->count('students.id'),
        ];
    }

    /** Whether there is anything to tell the school about. */
    public function hasAny(): bool
    {
        $summary = $this->summary();

        return $summary['classes'] > 0 || $summary['pupils'] > 0;
    }

    /** Forget the cached count — after an exit or a retirement. */
    public function forget(): void
    {
        $this->summary = null;
    }

    /**
     * The hidden classes, each with its currently-enrolled pupils.
     *
     * Classes with nobody left are listed too: a class that has emptied is
     * the one the school can now retire.
     *
     * @return Collection<int, Form>
     */
    public function classes(): Collection
    {
        $levels = $this->levels();

        if ($levels === []) {
            return collect();
        }

        $pupils = $this->pupilQuery($levels)
            ->with(['guardian', 'currentEnrollment.classSection.form'])
            ->orderBy('last_name')->orderBy('first_name')
            ->get()
            ->groupBy(fn (Student $s) => $s->currentEnrollment->classSection->form_id);

        return Form::active()->whereIn('school_level', $levels)->ordered()->get()
            ->each(fn (Form $form) => $form->setRelation('hiddenPupils', $pupils->get($form->id, collect())));
    }

    /**
     * Take a whole class off the roll at once.
     *
     * The same record as the single-pupil exit, pupil by pupil in one
     * transaction, so a class is either closed out or untouched.
     *
     * @param  array<int, int>  $studentIds  pupils to exit; must all be currently in a hidden class
     * @return int how many were recorded
     */
    public function exitPupils(array $studentIds, string $type, string $date, ?string $reason, ?string $destination): int
    {
        $levels = $this->levels();

        if ($levels === [] || $studentIds === []) {
            return 0;
        }

        $pupils = $this->pupilQuery($levels)->whereIn('students.id', $studentIds)->get();

        DB::transaction(function () use ($pupils, $type, $date, $reason, $destination) {
            foreach ($pupils as $pupil) {
                $this->exits->record($pupil, $type, $date, $reason, $destination);
            }
        });

        $this->forget();

        return $pupils->count();
    }

    /**
     * Retire the hidden classes that have nobody left in them.
     *
     * Deactivated, not deleted: their sections, curricula and old results all
     * hang off them. A class with pupils still enrolled is left alone — the
     * pupils have to leave first.
     *
     * @return int how many were retired
     */
    public function retireEmptyClasses(): int
    {
        $levels = $this->levels();

        if ($levels === []) {
            return 0;
        }

        // Re-selected on the base builder: pluck() keeps a select that is
        // already set, and the pupil query selects students.*.
        $occupied = $this->pupilQuery($levels)->toBase()
            ->select('class_sections.form_id')->distinct()->pluck('form_id');

        $retired = Form::active()->whereIn('school_level', $levels)
            ->whereNotIn('id', $occupied)
            ->update(['is_active' => false]);

        $this->forget();

        return $retired;
    }

    /**
     * Active pupils with a live enrolment, this session, in a class at one of
     * the given levels.
     *
     * @param  array<int, string>  $levels
     */
    private function pupilQuery(array $levels)
    {
        return Student::query()
            ->join('student_enrollments', function ($join) {
                $join->on('student_enrollments.student_id', '=', 'students.id')
                    ->where('student_enrollments.status', 'active')
                    ->whereIn('student_enrollments.academic_session_id', fn ($q) => $q
                        ->select('id')->from('academic_sessions')->where('is_current', true));
            })
            ->join('class_sections', 'class_sections.id', '=', 'student_enrollments.class_section_id')
            ->join('forms', 'forms.id', '=', 'class_sections.form_id')
            ->whereIn('forms.school_level', $levels)
            ->where('students.status', 'active')
            ->select('students.*')
            ->distinct();
    }
}
