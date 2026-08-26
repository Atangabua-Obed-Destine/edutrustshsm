<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Subject;
use Illuminate\Http\Request;

class SubjectController extends Controller
{
    public function index()
    {
        $subjects = Subject::with('department')->orderBy('name')->get();
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
            'department_id' => ['nullable', 'exists:departments,id'],
            'description' => ['nullable', 'string'],
        ]);

        $subject->update($validated);

        return redirect()->route('admin.subjects.index')
            ->with('success', 'Subject updated successfully.');
    }

    public function destroy(Subject $subject)
    {
        $subject->delete();

        return redirect()->route('admin.subjects.index')
            ->with('success', 'Subject deleted successfully.');
    }
}
