<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\AcademicSession;
use App\Models\MarksEntryDeadline;
use App\Models\Sequence;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class SequenceController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'exam-sequence';

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [static::can('results-control.deadline', ['deadline'])];
    }

    public function index()
    {
        $sequences = Sequence::orderBy('sequence_number')->get();

        // This year's mark-entry deadlines, one per sequence.
        $year = AcademicSession::current();
        $deadlines = $year
            ? MarksEntryDeadline::where('academic_session_id', $year->id)->get()->keyBy('sequence_id')
            : collect();

        return view('admin.sequences.index', compact('sequences', 'year', 'deadlines'));
    }

    /**
     * Set, move or clear the day mark entry closes for this sequence in the
     * current year. After it, teachers cannot enter or submit these marks.
     */
    public function deadline(Request $request, Sequence $sequence)
    {
        $validated = $request->validate(['closes_on' => ['nullable', 'date']]);
        $year = AcademicSession::current();

        if (! $year) {
            return back()->with('error', __('There is no current school year to set a deadline in.'));
        }

        if (blank($validated['closes_on'] ?? null)) {
            MarksEntryDeadline::where('academic_session_id', $year->id)->where('sequence_id', $sequence->id)->delete();

            return back()->with('success', __('No deadline for :sequence: teachers can enter marks at any time.', ['sequence' => $sequence->name]));
        }

        MarksEntryDeadline::updateOrCreate(
            ['academic_session_id' => $year->id, 'sequence_id' => $sequence->id],
            ['closes_on' => $validated['closes_on'], 'set_by' => auth()->id()]
        );

        return back()->with('success', __('Mark entry for :sequence closes at the end of :date.', [
            'sequence' => $sequence->name,
            'date' => \Illuminate\Support\Carbon::parse($validated['closes_on'])->format('d/m/Y'),
        ]));
    }

    public function create()
    {
        return redirect()->route('admin.sequences.index');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:sequences,name'],
            // The term average is weighted by this. No field wrote it before, so
            // every sequence sat at the same default and the "weighted" average
            // was silently an unweighted mean.
            'weight' => ['required', 'numeric', 'min:0.01', 'max:100'],
        ]);

        $validated['sequence_number'] = Sequence::max('sequence_number') + 1;

        Sequence::create($validated);

        return redirect()->route('admin.sequences.index')
            ->with('success', __('Exam sequence created successfully.'));
    }

    public function edit(Sequence $sequence)
    {
        return redirect()->route('admin.sequences.index');
    }

    public function update(Request $request, Sequence $sequence)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:sequences,name,' . $sequence->id],
            'weight' => ['required', 'numeric', 'min:0.01', 'max:100'],
        ]);

        $sequence->update($validated);

        return redirect()->route('admin.sequences.index')
            ->with('success', __('Exam sequence updated successfully.'));
    }

    public function destroy(Sequence $sequence)
    {
        if ($sequence->marks()->exists()) {
            return redirect()->route('admin.sequences.index')
                ->with('error', __('Cannot delete this sequence because it has marks recorded.'));
        }

        $sequence->delete();

        return redirect()->route('admin.sequences.index')
            ->with('success', __('Exam sequence deleted successfully.'));
    }
}
