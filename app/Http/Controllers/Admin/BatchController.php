<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use Illuminate\Http\Request;

class BatchController extends Controller
{
    public function index()
    {
        $batches = Batch::orderBy('name')->get();
        return view('admin.batches.index', compact('batches'));
    }

    public function create()
    {
        return view('admin.batches.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:batches,name'],
            'shortcode' => ['required', 'string', 'max:20', 'unique:batches,shortcode'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        Batch::create($validated);

        return redirect()->route('admin.batches.index')
            ->with('success', 'Batch created successfully.');
    }

    public function edit(Batch $batch)
    {
        return view('admin.batches.edit', compact('batch'));
    }

    public function update(Request $request, Batch $batch)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:batches,name,' . $batch->id],
            'shortcode' => ['required', 'string', 'max:20', 'unique:batches,shortcode,' . $batch->id],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $batch->update(array_merge($validated, [
            'is_active' => $request->boolean('is_active'),
        ]));

        return redirect()->route('admin.batches.index')
            ->with('success', 'Batch updated successfully.');
    }

    public function destroy(Batch $batch)
    {
        $batch->delete();

        return redirect()->route('admin.batches.index')
            ->with('success', 'Batch deleted successfully.');
    }
}
