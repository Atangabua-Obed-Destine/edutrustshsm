<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\Department;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class SubjectController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'subject';

    public function index()
    {
        $subjects = Subject::with('department')->forCurrentLevel()->orderBy('name')->get();
        return view('admin.subjects.index', compact('subjects'));
    }

    public function create()
    {
        $departments = Department::orderBy('name')->get();
        return view('admin.subjects.create', compact('departments'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:20', \Illuminate\Validation\Rule::unique('subjects', 'code')->where('branch_id', \App\Support\BranchContext::current())],
            'school_level' => ['nullable', 'in:nursery_primary,secondary'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'description' => ['nullable', 'string'],
        ]);

        Subject::create($validated);

        return redirect()->route('admin.subjects.index')
            ->with('success', 'Subject created successfully.');
    }

    public function edit(Subject $subject)
    {
        $departments = Department::orderBy('name')->get();
        return view('admin.subjects.edit', compact('subject', 'departments'));
    }

    public function update(Request $request, Subject $subject)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:20', \Illuminate\Validation\Rule::unique('subjects', 'code')->where('branch_id', \App\Support\BranchContext::current())->ignore($subject->id)],
            'school_level' => ['nullable', 'in:nursery_primary,secondary'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'description' => ['nullable', 'string'],
        ]);

        $subject->update($validated);

        return redirect()->route('admin.subjects.index')
            ->with('success', 'Subject updated successfully.');
    }

    public function destroy(Subject $subject)
    {
        // Marks and results cascade off a subject.
        $inUse = \Illuminate\Support\Facades\DB::table('marks')->where('subject_id', $subject->id)->exists()
            || \Illuminate\Support\Facades\DB::table('student_subjects')->where('subject_id', $subject->id)->exists();

        if ($inUse) {
            return back()->with('error', __('Pupils have been registered or marked in this subject, so it cannot be deleted. Deactivate it instead.'));
        }

        $subject->delete();

        return redirect()->route('admin.subjects.index')
            ->with('success', 'Subject deleted successfully.');
    }
}
