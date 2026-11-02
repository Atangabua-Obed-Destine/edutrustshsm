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

        // What the dates mean for the rest of the system, said plainly:
        // attendance and report cards only count days inside term dates.
        $warnings = [];
        $current = $terms->firstWhere('is_current', true);
        $today = now()->startOfDay();
        if (! $current) {
            $warnings[] = __('No term is marked current. Marks entry, registration and the dashboards all start from the current term.');
        } elseif ($today->lt($current->start_date) || $today->gt($current->end_date)) {
            $warnings[] = __('Today (:today) is outside the current term (:start – :end). Registers taken today will not count on report cards, which only count days inside term dates.', [
                'today' => $today->translatedFormat('j M Y'),
                'start' => $current->start_date->translatedFormat('j M Y'),
                'end' => $current->end_date->translatedFormat('j M Y'),
            ]);
        }
        foreach ($terms as $t) {
            $days = $t->start_date->diffInDays($t->end_date);
            if ($days < 28) {
                $warnings[] = __(':term lasts only :days days. A school term usually runs ten to fourteen weeks; check the dates.', ['term' => $t->name, 'days' => (int) $days]);
            }
        }

        return view('admin.terms.index', compact('terms', 'warnings'));
    }

    /** Terms may not overlap: a day must belong to one term only. */
    private function overlapping(string $start, string $end, ?int $ignore = null): ?Term
    {
        return Term::query()
            ->when($ignore, fn ($q) => $q->where('id', '!=', $ignore))
            ->whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start)
            ->first();
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
            return back()->withInput()->withErrors(['term_number' => __('This term number already exists.')]);
        }

        if ($clash = $this->overlapping($validated['start_date'], $validated['end_date'])) {
            return back()->withInput()->withErrors(['start_date' => __('These dates overlap :term. A day belongs to one term only.', ['term' => $clash->name])]);
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
            return back()->withInput()->withErrors(['term_number' => __('This term number already exists.')]);
        }

        if ($clash = $this->overlapping($validated['start_date'], $validated['end_date'], $term->id)) {
            return back()->withInput()->withErrors(['start_date' => __('These dates overlap :term. A day belongs to one term only.', ['term' => $clash->name])]);
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
        // Results, enrolments and sequence mappings cascade off a term.
        $inUse = \Illuminate\Support\Facades\DB::table('term_results')->where('term_id', $term->id)->exists()
            || \Illuminate\Support\Facades\DB::table('student_enrollments')->where('term_id', $term->id)->exists()
            || \Illuminate\Support\Facades\DB::table('form_sequence')->where('term_id', $term->id)->exists();

        if ($inUse) {
            return back()->with('error', __('This term has enrolments, results or sequences recorded against it, so it cannot be deleted.'));
        }

        $term->delete();

        return redirect()->route('admin.terms.index')
            ->with('success', 'Term deleted successfully.');
    }
}
