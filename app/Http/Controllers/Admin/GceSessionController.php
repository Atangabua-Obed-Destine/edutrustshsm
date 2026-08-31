<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\AcademicSession;
use App\Models\Form;
use App\Models\GceRegistrationSession;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * GCE exam series — "June 2026, Ordinary Level".
 *
 * Subject-count limits and fees are per series rather than in code: the Board
 * changes them between years, and a school should not need a deployment to
 * follow this year's rules.
 */
class GceSessionController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'gce-registration';

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('gce-registration.edit', ['setStatus']),
        ];
    }

    public function index()
    {
        $sessions = GceRegistrationSession::with(['academicSession', 'forms'])
            ->withCount('candidates')
            ->orderByDesc('exam_year')
            ->orderBy('level')
            ->get();

        return view('admin.gce.sessions.index', compact('sessions'));
    }

    public function create()
    {
        return view('admin.gce.sessions.form', [
            'session' => new GceRegistrationSession(['exam_year' => now()->year, 'min_subjects' => 1, 'max_subjects' => 9]),
            'academicSessions' => AcademicSession::orderByDesc('start_date')->get(),
            'forms' => Form::where('is_active', true)->orderBy('display_order')->get(),
            'selectedForms' => [],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);

        $session = GceRegistrationSession::create($validated);
        $session->forms()->sync($request->input('form_ids', []));

        return redirect()->route('admin.gce.sessions.index')
            ->with('success', __('Exam series created.'));
    }

    public function edit(GceRegistrationSession $session)
    {
        return view('admin.gce.sessions.form', [
            'session' => $session,
            'academicSessions' => AcademicSession::orderByDesc('start_date')->get(),
            'forms' => Form::where('is_active', true)->orderBy('display_order')->get(),
            'selectedForms' => $session->forms->pluck('id')->all(),
        ]);
    }

    public function update(Request $request, GceRegistrationSession $session)
    {
        $session->update($this->validated($request, $session));
        $session->forms()->sync($request->input('form_ids', []));

        return redirect()->route('admin.gce.sessions.index')
            ->with('success', __('Exam series updated.'));
    }

    public function destroy(GceRegistrationSession $session)
    {
        // Deleting a series would take its entries with it, which is not a thing
        // to do by accident once candidates are on the Board's list.
        if ($session->candidates()->exists()) {
            return back()->with('error', __('This series has candidates entered, so it cannot be deleted. Close it instead.'));
        }

        $session->delete();

        return back()->with('success', __('Exam series deleted.'));
    }

    public function setStatus(Request $request, GceRegistrationSession $session)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:draft,open,closed,submitted'],
        ]);

        $session->update($validated);

        return back()->with('success', __('Exam series is now :status.', ['status' => __(ucfirst($validated['status']))]));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?GceRegistrationSession $session = null): array
    {
        return $request->validate([
            'academic_session_id' => ['required', 'exists:academic_sessions,id'],
            'name' => ['required', 'string', 'max:100'],
            'level' => ['required', 'in:o_level,a_level'],
            'exam_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'centre_number' => ['nullable', 'string', 'max:20'],
            'opens_on' => ['nullable', 'date'],
            'closes_on' => ['nullable', 'date', 'after_or_equal:opens_on'],
            'min_subjects' => ['required', 'integer', 'min:1', 'max:20'],
            'max_subjects' => ['required', 'integer', 'min:1', 'max:20', 'gte:min_subjects'],
            'fee_per_subject' => ['required', 'numeric', 'min:0'],
            'base_fee' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:draft,open,closed,submitted'],
        ]);
    }
}
