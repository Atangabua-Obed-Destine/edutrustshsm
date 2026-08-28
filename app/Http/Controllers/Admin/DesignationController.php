<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\Designation;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class DesignationController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'designation';

    public function index()
    {
        $items = Designation::orderBy('title')->get();
        return view('admin.hr.designations.index', compact('items'));
    }

    public function store(Request $request)
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:191']]);
        $item = Designation::create($data + ['status' => true]);
        return back()->with('success', __('Designation created.'));
    }

    public function update(Request $request, Designation $designation)
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:191'], 'status' => ['required', 'boolean']]);
        $designation->update($data);
        return back()->with('success', __('Designation updated.'));
    }

    public function destroy(Designation $designation)
    {
        $designation->delete();
        return back()->with('success', __('Designation deleted.'));
    }
}
