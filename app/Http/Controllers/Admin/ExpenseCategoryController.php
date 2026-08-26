<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ExpenseCategoryController extends Controller
{
    public function index()
    {
        $categories = ExpenseCategory::orderBy('title')->get();
        return view('admin.account.expense-category.index', compact('categories'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:191', 'unique:expense_categories,title'],
        ]);

        $category = ExpenseCategory::create([
            'title' => $validated['title'],
            'slug' => Str::slug($validated['title']),
            'status' => true,
        ]);

        AuditLog::log('created', ExpenseCategory::class, $category->id, null, $category->toArray());

        return redirect()->route('admin.account.expense-category.index')
            ->with('success', __('Expense category created successfully.'));
    }

    public function update(Request $request, ExpenseCategory $expense_category)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:191', 'unique:expense_categories,title,' . $expense_category->id],
            'status' => ['required', 'boolean'],
        ]);

        $old = $expense_category->toArray();
        $expense_category->update([
            'title' => $validated['title'],
            'slug' => Str::slug($validated['title']),
            'status' => $validated['status'],
        ]);

        AuditLog::log('updated', ExpenseCategory::class, $expense_category->id, $old, $expense_category->toArray());

        return redirect()->route('admin.account.expense-category.index')
            ->with('success', __('Expense category updated successfully.'));
    }

    public function destroy(ExpenseCategory $expense_category)
    {
        $old = $expense_category->toArray();
        $expense_category->delete();

        AuditLog::log('deleted', ExpenseCategory::class, $old['id'], $old, null);

        return redirect()->route('admin.account.expense-category.index')
            ->with('success', __('Expense category deleted successfully.'));
    }
}
