<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Controller;
use App\Services\OutOfScopeService;
use App\Services\StudentExitService;
use App\Support\LevelContext;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;

/**
 * Pupils and classes at a level this school no longer runs.
 *
 * Seeing them is seeing pupils; closing them out is recording an exit, so
 * the two halves take the two existing permissions rather than a new one.
 */
class OutOfScopeController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'student';

    public function __construct(private OutOfScopeService $hidden) {}

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('student-exit.create', ['exitClass']),
            static::can('form.edit', ['retireClasses']),
        ];
    }

    public function index()
    {
        // Nothing is hidden in a school that runs every level.
        abort_if($this->hidden->levels() === [], 404);

        return view('admin.students.out-of-scope', [
            'classes' => $this->hidden->classes(),
            'summary' => $this->hidden->summary(),
            'hiddenLabels' => array_intersect_key(LevelContext::labels(), array_flip($this->hidden->levels())),
            'types' => array_intersect_key(StudentExitService::types(), array_flip([
                StudentExitService::GRADUATED, StudentExitService::TRANSFERRED,
            ])),
        ]);
    }

    /** Close out a class: every ticked pupil leaves the same way on the same day. */
    public function exitClass(Request $request)
    {
        abort_if($this->hidden->levels() === [], 404);

        $validated = $request->validate([
            'students' => ['required', 'array', 'min:1'],
            'students.*' => ['integer'],
            'type' => ['required', Rule::in([StudentExitService::GRADUATED, StudentExitService::TRANSFERRED])],
            'left_on' => ['required', 'date', 'before_or_equal:today'],
            'reason' => ['nullable', 'string', 'max:500'],
            'destination_school' => ['nullable', 'required_if:type,transferred', 'string', 'max:200'],
        ], [
            'students.required' => __('Tick at least one pupil.'),
            'destination_school.required_if' => __('Name the school the pupils are transferring to.'),
            'left_on.before_or_equal' => __('A pupil cannot have left on a future date.'),
        ]);

        $count = $this->hidden->exitPupils(
            array_map('intval', $validated['students']),
            $validated['type'],
            $validated['left_on'],
            $validated['reason'] ?? null,
            $validated['destination_school'] ?? null
        );

        return redirect()->route('admin.students.out-of-scope')
            ->with('success', trans_choice(
                '{0} Nobody was taken off the roll.|{1} :count pupil has been taken off the roll.|[2,*] :count pupils have been taken off the roll.',
                $count, ['count' => $count]
            ));
    }

    /** Deactivate the hidden classes that have emptied. */
    public function retireClasses()
    {
        abort_if($this->hidden->levels() === [], 404);

        $count = $this->hidden->retireEmptyClasses();

        return redirect()->route('admin.students.out-of-scope')
            ->with('success', trans_choice(
                '{0} No class was empty, so none was retired.|{1} :count class has been retired.|[2,*] :count classes have been retired.',
                $count, ['count' => $count]
            ));
    }
}
