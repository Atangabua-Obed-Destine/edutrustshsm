<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\GceSubject;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Validation\Rule;

/**
 * The GCE Board's subject catalogue.
 *
 * Kept separate from the school's own subjects because the Board's codes are
 * what the entry file is read by, and a school's internal subject list rarely
 * lines up with them one-to-one. Where it does, the two can be mapped.
 */
class GceSubjectController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'gce-registration';

    public function index(Request $request)
    {
        $level = $request->input('level', 'o_level');

        return view('admin.gce.subjects.index', [
            'level' => $level,
            'gceSubjects' => GceSubject::with('subject')->forLevel($level)->orderBy('code')->get(),
            'subjects' => Subject::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        GceSubject::create($this->validated($request));

        return back()->with('success', __('Board subject added.'));
    }

    public function update(Request $request, GceSubject $gceSubject)
    {
        $gceSubject->update($this->validated($request, $gceSubject));

        return back()->with('success', __('Board subject updated.'));
    }

    public function destroy(GceSubject $gceSubject)
    {
        // Removing a subject candidates are entered for would silently drop it
        // from their entries.
        if ($gceSubject->candidates()->exists()) {
            return back()->with('error', __('Candidates are entered for this subject, so it cannot be deleted. Deactivate it instead.'));
        }

        $gceSubject->delete();

        return back()->with('success', __('Board subject deleted.'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?GceSubject $subject = null): array
    {
        $validated = $request->validate([
            'code' => [
                'required', 'string', 'max:20',
                Rule::unique('gce_subjects')
                    ->where(fn ($q) => $q->where('level', $request->input('level'))->where('branch_id', $subject?->branch_id))
                    ->ignore($subject?->id),
            ],
            'name' => ['required', 'string', 'max:150'],
            'level' => ['required', 'in:o_level,a_level'],
            'subject_id' => ['nullable', 'exists:subjects,id'],
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        return $validated;
    }
}
