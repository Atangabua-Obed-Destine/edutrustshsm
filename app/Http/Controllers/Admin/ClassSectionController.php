<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassSection;
use App\Models\Form;
use App\Models\Room;
use App\Models\User;
use Illuminate\Http\Request;

class ClassSectionController extends Controller
{
    public function index()
    {
        $classSections = ClassSection::with(['form', 'classTeacher', 'room'])
            ->whereHas('form', fn($q) => $q->forCurrentLevel())
            ->orderBy('form_id')
            ->orderBy('section')
            ->get();

        return view('admin.class-sections.index', compact('classSections'));
    }

    public function create()
    {
        $forms = Form::forCurrentLevel()->ordered()->get();
        $teachers = User::where('role', 'teacher')->where('is_active', true)->orderBy('first_name')->get();
        $rooms = Room::where('is_active', true)->orderBy('name')->get();

        return view('admin.class-sections.create', compact('forms', 'teachers', 'rooms'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'form_id' => ['required', 'exists:forms,id'],
            'section' => ['required', 'string', 'max:5'],
            'name' => ['required', 'string', 'max:100'],
            'class_teacher_id' => ['nullable', 'exists:users,id'],
            'room_id' => ['nullable', 'exists:rooms,id'],
            'max_students' => ['required', 'integer', 'min:1', 'max:200'],
        ]);

        ClassSection::create($validated);

        return redirect()->route('admin.class-sections.index')
            ->with('success', 'Class section created successfully.');
    }

    public function edit(ClassSection $classSection)
    {
        $forms = Form::forCurrentLevel()->ordered()->get();
        $teachers = User::where('role', 'teacher')->where('is_active', true)->orderBy('first_name')->get();
        $rooms = Room::where('is_active', true)->orderBy('name')->get();

        return view('admin.class-sections.edit', compact('classSection', 'forms', 'teachers', 'rooms'));
    }

    public function update(Request $request, ClassSection $classSection)
    {
        $validated = $request->validate([
            'form_id' => ['required', 'exists:forms,id'],
            'section' => ['required', 'string', 'max:5'],
            'name' => ['required', 'string', 'max:100'],
            'class_teacher_id' => ['nullable', 'exists:users,id'],
            'room_id' => ['nullable', 'exists:rooms,id'],
            'max_students' => ['required', 'integer', 'min:1', 'max:200'],
        ]);

        $classSection->update($validated);

        return redirect()->route('admin.class-sections.index')
            ->with('success', 'Class section updated successfully.');
    }

    public function destroy(ClassSection $classSection)
    {
        $classSection->delete();

        return redirect()->route('admin.class-sections.index')
            ->with('success', 'Class section deleted successfully.');
    }
}
