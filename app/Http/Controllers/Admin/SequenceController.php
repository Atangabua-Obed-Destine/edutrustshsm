<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\Sequence;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class SequenceController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'exam-sequence';

    public function index()
    {
        $sequences = Sequence::orderBy('sequence_number')->get();

        return view('admin.sequences.index', compact('sequences'));
    }

    public function create()
    {
        return redirect()->route('admin.sequences.index');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:sequences,name'],
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
