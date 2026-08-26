<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FeeCategory;
use Illuminate\Http\Request;

class FeeCategoryController extends Controller
{
    public function index()
    {
        $categories = FeeCategory::withCount('feeStructures')
            ->orderBy('name')
            ->get();

        return view('admin.fees.categories.index', compact('categories'));
    }

    public function create()
    {
        return view('admin.fees.categories.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:20', 'unique:fee_categories,code'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_mandatory' => ['boolean'],
            'is_refundable' => ['boolean'],
            'is_tuition' => ['boolean'],
            'is_boarding' => ['boolean'],
        ]);

        $validated['is_mandatory'] = $request->boolean('is_mandatory');
        $validated['is_refundable'] = $request->boolean('is_refundable');
        $validated['is_tuition'] = $request->boolean('is_tuition');
        $validated['is_boarding'] = $request->boolean('is_boarding');
        $validated['is_active'] = true;

        FeeCategory::create($validated);

        return redirect()->route('admin.fee-categories.index')
            ->with('success', 'Fee category created successfully.');
    }

    public function edit(FeeCategory $feeCategory)
    {
        return view('admin.fees.categories.edit', compact('feeCategory'));
    }

    public function update(Request $request, FeeCategory $feeCategory)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:20', 'unique:fee_categories,code,' . $feeCategory->id],
            'description' => ['nullable', 'string', 'max:500'],
            'is_mandatory' => ['boolean'],
            'is_refundable' => ['boolean'],
            'is_tuition' => ['boolean'],
            'is_boarding' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        $validated['is_mandatory'] = $request->boolean('is_mandatory');
        $validated['is_refundable'] = $request->boolean('is_refundable');
        $validated['is_tuition'] = $request->boolean('is_tuition');
        $validated['is_boarding'] = $request->boolean('is_boarding');
        $validated['is_active'] = $request->boolean('is_active');

        $feeCategory->update($validated);

        return redirect()->route('admin.fee-categories.index')
            ->with('success', 'Fee category updated successfully.');
    }

    public function destroy(FeeCategory $feeCategory)
    {
        if ($feeCategory->feeStructures()->exists()) {
            return back()->with('error', 'Cannot delete — this category has fee structures assigned.');
        }

        $feeCategory->delete();

        return redirect()->route('admin.fee-categories.index')
            ->with('success', 'Fee category deleted.');
    }
}
