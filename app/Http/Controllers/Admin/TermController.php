<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\Term;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class TermController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'term';

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('term.edit', ['setCurrent']),
        ];
    }

    public function index()
    {
        $terms = Term::orderBy('term_number')->get();
        return view('admin.terms.index', compact('terms'));
    }

    public function create()
    {
        return view('admin.terms.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'term_number' => ['required', 'integer', 'min:1', 'max:3'],
            'name' => ['required', 'string', 'max:50'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
        ]);

        $exists = Term::where('term_number', $validated['term_number'])->exists();

        if ($exists) {
            return back()->withInput()->withErrors(['term_number' => 'This term number already exists.']);
        }

        Term::create($validated);

        return redirect()->route('admin.terms.index')
            ->with('success', 'Term created successfully.');
    }

    public function edit(Term $term)
    {
        return view('admin.terms.edit', compact('term'));
    }

    public function update(Request $request, Term $term)
    {
        $validated = $request->validate([
            'term_number' => ['required', 'integer', 'min:1', 'max:3'],
            'name' => ['required', 'string', 'max:50'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'is_current' => ['nullable', 'boolean'],
        ]);

        $exists = Term::where('term_number', $validated['term_number'])
            ->where('id', '!=', $term->id)->exists();

        if ($exists) {
            return back()->withInput()->withErrors(['term_number' => 'This term number already exists.']);
        }

        $isCurrent = (bool) ($validated['is_current'] ?? false);
        unset($validated['is_current']);

        if ($isCurrent) {
            Term::where('id', '!=', $term->id)->update(['is_current' => false]);
            $validated['is_current'] = true;
        } else {
            $validated['is_current'] = false;
        }

        $term->update($validated);

        return redirect()->route('admin.terms.index')
            ->with('success', __('Term updated successfully.'));
    }

    public function setCurrent(Term $term)
    {
        Term::where('id', '!=', $term->id)->update(['is_current' => false]);
        $term->update(['is_current' => true]);

        return redirect()->route('admin.terms.index')
            ->with('success', __(':name is now the current term.', ['name' => $term->name]));
    }

    public function destroy(Term $term)
    {
        $term->delete();

        return redirect()->route('admin.terms.index')
            ->with('success', 'Term deleted successfully.');
    }
}
