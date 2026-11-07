<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\AcademicSession;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class AcademicSessionController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'academic-session';

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('academic-session.edit', ['activate']),
        ];
    }

    public function index()
    {
        $sessions = AcademicSession::orderByDesc('start_date')->get();
        return view('admin.sessions.index', compact('sessions'));
    }

    public function create()
    {
        return view('admin.sessions.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50', \Illuminate\Validation\Rule::unique('academic_sessions', 'name')->where('branch_id', \App\Support\BranchContext::current())],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
        ]);

        AcademicSession::create($validated);

        return redirect()->route('admin.sessions.index')
            ->with('success', 'Academic session created successfully.');
    }

    public function edit(AcademicSession $session)
    {
        return view('admin.sessions.edit', compact('session'));
    }

    public function update(Request $request, AcademicSession $session)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50', \Illuminate\Validation\Rule::unique('academic_sessions', 'name')->where('branch_id', \App\Support\BranchContext::current())->ignore($session->id)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
        ]);

        $session->update($validated);

        return redirect()->route('admin.sessions.index')
            ->with('success', 'Academic session updated successfully.');
    }

    public function destroy(AcademicSession $session)
    {
        if ($session->is_current) {
            return back()->with('error', 'Cannot delete the current active session.');
        }

        // Its enrolments, results and fees would go with it, at database
        // level and with no trace: a year with pupils in it stays.
        if ($session->enrollments()->exists()) {
            return back()->with('error', __('Pupils are enrolled in this year, so it cannot be deleted.'));
        }

        $session->delete();

        return redirect()->route('admin.sessions.index')
            ->with('success', 'Academic session deleted successfully.');
    }

    public function activate(AcademicSession $session)
    {
        // A year recorded from before the system is history: making it
        // current would open registers, billing and marks entry on it.
        if ($session->is_recorded) {
            return back()->with('error', __('This year was recorded from before the system and cannot become the current year.'));
        }

        // Deactivate all other sessions. "past" is the column's own word;
        // "completed" is not in it and strict MySQL refused the rollover.
        AcademicSession::where('is_current', true)->update(['is_current' => false, 'status' => 'past']);

        $session->update(['is_current' => true, 'status' => 'active']);

        return redirect()->route('admin.sessions.index')
            ->with('success', "Session '{$session->name}' is now the active session.");
    }
}
