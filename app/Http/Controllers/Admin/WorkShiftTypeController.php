<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WorkShiftType;
use Illuminate\Http\Request;

class WorkShiftTypeController extends Controller
{
    public function index()
    {
        $items = WorkShiftType::orderBy('title')->get();
        return view('admin.hr.work-shifts.index', compact('items'));
    }

    public function store(Request $request)
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:191']]);
        WorkShiftType::create($data + ['status' => true]);
        return back()->with('success', __('Work shift created.'));
    }

    public function update(Request $request, WorkShiftType $work_shift)
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:191'], 'status' => ['required', 'boolean']]);
        $work_shift->update($data);
        return back()->with('success', __('Work shift updated.'));
    }

    public function destroy(WorkShiftType $work_shift)
    {
        $work_shift->delete();
        return back()->with('success', __('Work shift deleted.'));
    }
}
