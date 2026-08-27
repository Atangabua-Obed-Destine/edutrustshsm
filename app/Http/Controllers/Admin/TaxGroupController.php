<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\AuditLog;
use App\Models\TaxGroup;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class TaxGroupController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'tax-group';

    public function index()
    {
        $groups = TaxGroup::withCount('brackets')->orderBy('display_order')->get();
        return view('admin.hr.tax.groups.index', compact('groups'));
    }

    public function create()
    {
        return view('admin.hr.tax.groups.create');
    }

    public function store(Request $request)
    {
        $data = $this->validateGroup($request);
        $group = TaxGroup::create($data);
        AuditLog::log('created', TaxGroup::class, $group->id, null, $group->toArray());
        return redirect()->route('admin.tax-groups.index')->with('success', __('Tax group created.'));
    }

    public function edit(TaxGroup $tax_group)
    {
        return view('admin.hr.tax.groups.edit', ['group' => $tax_group]);
    }

    public function update(Request $request, TaxGroup $tax_group)
    {
        $data = $this->validateGroup($request);
        $tax_group->update($data);
        return redirect()->route('admin.tax-groups.index')->with('success', __('Tax group updated.'));
    }

    public function destroy(TaxGroup $tax_group)
    {
        $tax_group->delete();
        return back()->with('success', __('Tax group deleted.'));
    }

    private function validateGroup(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:191'],
            'code' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'is_progressive' => ['required', 'boolean'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'display_order' => ['nullable', 'integer', 'min:0'],
        ]) + ['status' => true];
    }
}
