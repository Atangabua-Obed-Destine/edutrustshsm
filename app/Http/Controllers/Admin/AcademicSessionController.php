<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use Illuminate\Http\Request;

class AcademicSessionController extends Controller
{
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

        $session->delete();

        return redirect()->route('admin.sessions.index')
            ->with('success', 'Academic session deleted successfully.');
    }

    public function activate(AcademicSession $session)
    {
        // Deactivate all other sessions
        AcademicSession::where('is_current', true)->update(['is_current' => false, 'status' => 'completed']);

        $session->update(['is_current' => true, 'status' => 'active']);

        return redirect()->route('admin.sessions.index')
            ->with('success', "Session '{$session->name}' is now the active session.");
    }
}
