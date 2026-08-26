<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeductionType;
use Illuminate\Http\Request;

class DeductionTypeController extends Controller
{
    public function index()
    {
        $items = DeductionType::orderBy('title')->get();
        return view('admin.hr.deduction-types.index', compact('items'));
    }

    public function store(Request $request)
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:191']]);
        DeductionType::create($data + ['status' => true]);
        return back()->with('success', __('Deduction type created.'));
    }

    public function update(Request $request, DeductionType $deduction_type)
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:191'], 'status' => ['required', 'boolean']]);
        $deduction_type->update($data);
        return back()->with('success', __('Deduction type updated.'));
    }

    public function destroy(DeductionType $deduction_type)
    {
        $deduction_type->delete();
        return back()->with('success', __('Deduction type deleted.'));
    }
}
