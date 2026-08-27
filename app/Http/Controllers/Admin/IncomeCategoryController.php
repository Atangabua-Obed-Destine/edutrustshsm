<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\AuditLog;
use App\Models\IncomeCategory;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Str;

class IncomeCategoryController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'income-category';

    public function index()
    {
        $categories = IncomeCategory::orderBy('title')->get();
        return view('admin.account.income-category.index', compact('categories'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:191', 'unique:income_categories,title'],
        ]);

        $category = IncomeCategory::create([
            'title' => $validated['title'],
            'slug' => Str::slug($validated['title']),
            'status' => true,
        ]);

        AuditLog::log('created', IncomeCategory::class, $category->id, null, $category->toArray());

        return redirect()->route('admin.account.income-category.index')
            ->with('success', __('Income category created successfully.'));
    }

    public function update(Request $request, IncomeCategory $income_category)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:191', 'unique:income_categories,title,' . $income_category->id],
            'status' => ['required', 'boolean'],
        ]);

        $old = $income_category->toArray();
        $income_category->update([
            'title' => $validated['title'],
            'slug' => Str::slug($validated['title']),
            'status' => $validated['status'],
        ]);

        AuditLog::log('updated', IncomeCategory::class, $income_category->id, $old, $income_category->toArray());

        return redirect()->route('admin.account.income-category.index')
            ->with('success', __('Income category updated successfully.'));
    }

    public function destroy(IncomeCategory $income_category)
    {
        $old = $income_category->toArray();
        $income_category->delete();

        AuditLog::log('deleted', IncomeCategory::class, $old['id'], $old, null);

        return redirect()->route('admin.account.income-category.index')
            ->with('success', __('Income category deleted successfully.'));
    }
}
