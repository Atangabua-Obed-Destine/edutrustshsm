<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\Form;
use App\Models\Stream;
use App\Support\LevelContext;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;

class FormController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'form';

    public function index()
    {
        $forms = Form::with('streams')->forCurrentLevel()->ordered()->get();
        return view('admin.forms.index', compact('forms'));
    }

    public function create()
    {
        $streams = Stream::where('is_active', true)->get();
        $currentLevel = LevelContext::current();
        return view('admin.forms.create', compact('streams', 'currentLevel'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50', 'unique:forms,name'],
            'short_name' => ['required', 'string', 'max:20'],
            'school_level' => ['required', Rule::in(array_keys(Form::LEVELS))],
            'level' => ['required', Rule::in(Form::levelsFor($request->input('school_level', '')))],
            'education_system' => ['required', 'in:english,french'],
            'display_order' => ['required', 'integer', 'min:1'],
            'has_streams' => ['boolean'],
            'streams' => ['array'],
            'streams.*' => ['exists:streams,id'],
        ]);

        $form = Form::create([
            'name' => $validated['name'],
            'short_name' => $validated['short_name'],
            'school_level' => $validated['school_level'],
            'level' => $validated['level'],
            'education_system' => $validated['education_system'],
            'display_order' => $validated['display_order'],
            'has_streams' => $request->boolean('has_streams'),
        ]);

        if ($request->boolean('has_streams') && !empty($validated['streams'])) {
            $form->streams()->sync($validated['streams']);
        }

        return redirect()->route('admin.forms.index')
            ->with('success', 'Form created successfully.');
    }

    public function edit(Form $form)
    {
        $form->load('streams');
        $streams = Stream::where('is_active', true)->get();
        return view('admin.forms.edit', compact('form', 'streams'));
    }

    public function update(Request $request, Form $form)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50', 'unique:forms,name,' . $form->id],
            'short_name' => ['required', 'string', 'max:20'],
            'school_level' => ['required', Rule::in(array_keys(Form::LEVELS))],
            'level' => ['required', Rule::in(Form::levelsFor($request->input('school_level', '')))],
            'education_system' => ['required', 'in:english,french'],
            'display_order' => ['required', 'integer', 'min:1'],
            'has_streams' => ['boolean'],
            'streams' => ['array'],
            'streams.*' => ['exists:streams,id'],
        ]);

        $form->update([
            'name' => $validated['name'],
            'short_name' => $validated['short_name'],
            'school_level' => $validated['school_level'],
            'level' => $validated['level'],
            'education_system' => $validated['education_system'],
            'display_order' => $validated['display_order'],
            'has_streams' => $request->boolean('has_streams'),
        ]);

        if ($request->boolean('has_streams') && !empty($validated['streams'])) {
            $form->streams()->sync($validated['streams']);
        } else {
            $form->streams()->detach();
        }

        return redirect()->route('admin.forms.index')
            ->with('success', 'Form updated successfully.');
    }

    public function destroy(Form $form)
    {
        $form->delete();

        return redirect()->route('admin.forms.index')
            ->with('success', 'Form deleted successfully.');
    }
}
