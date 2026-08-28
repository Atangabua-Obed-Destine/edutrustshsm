<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\AllowanceType;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class AllowanceTypeController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'allowance-type';

    public function index()
    {
        $items = AllowanceType::orderBy('title')->get();
        return view('admin.hr.allowance-types.index', compact('items'));
    }

    public function store(Request $request)
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:191']]);
        AllowanceType::create($data + ['status' => true]);
        return back()->with('success', __('Allowance type created.'));
    }

    public function update(Request $request, AllowanceType $allowance_type)
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:191'], 'status' => ['required', 'boolean']]);
        $allowance_type->update($data);
        return back()->with('success', __('Allowance type updated.'));
    }

    public function destroy(AllowanceType $allowance_type)
    {
        $allowance_type->delete();
        return back()->with('success', __('Allowance type deleted.'));
    }
}
