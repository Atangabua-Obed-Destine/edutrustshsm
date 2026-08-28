<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Str;

class ExpenseCategoryController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'expense-category';

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


        return redirect()->route('admin.account.expense-category.index')
            ->with('success', __('Expense category updated successfully.'));
    }

    public function destroy(ExpenseCategory $expense_category)
    {
        $old = $expense_category->toArray();
        $expense_category->delete();


        return redirect()->route('admin.account.expense-category.index')
            ->with('success', __('Expense category deleted successfully.'));
    }
}
