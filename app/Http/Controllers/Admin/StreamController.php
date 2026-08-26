<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Stream;
use Illuminate\Http\Request;

class StreamController extends Controller
{
    public function index()
    {
        $streams = Stream::withCount('forms')->orderBy('name')->get();
        return view('admin.streams.index', compact('streams'));
    }

    public function create()
    {
        return view('admin.streams.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'code' => ['required', 'string', 'max:10', \Illuminate\Validation\Rule::unique('streams', 'code')->where('branch_id', \App\Support\BranchContext::current())],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        Stream::create([
            'name' => $validated['name'],
            'code' => strtoupper($validated['code']),
            'description' => $validated['description'],
            'is_general' => $request->boolean('is_general'),
        ]);

        return redirect()->route('admin.streams.index')
            ->with('success', 'Stream created successfully.');
    }

    public function edit(Stream $stream)
    {
        return view('admin.streams.edit', compact('stream'));
    }

    public function update(Request $request, Stream $stream)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'code' => ['required', 'string', 'max:10', \Illuminate\Validation\Rule::unique('streams', 'code')->where('branch_id', \App\Support\BranchContext::current())->ignore($stream->id)],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ]);

        $stream->update([
            'name' => $validated['name'],
            'code' => strtoupper($validated['code']),
            'description' => $validated['description'],
            'is_general' => $request->boolean('is_general'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('admin.streams.index')
            ->with('success', 'Stream updated successfully.');
    }

    public function destroy(Stream $stream)
    {
        if ($stream->forms()->count() > 0) {
            return back()->with('error', 'Cannot delete a stream that is assigned to forms.');
        }

        $stream->delete();

        return redirect()->route('admin.streams.index')
            ->with('success', 'Stream deleted successfully.');
    }
}
