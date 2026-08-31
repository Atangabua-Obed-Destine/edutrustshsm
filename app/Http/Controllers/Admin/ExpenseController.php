<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\Budget;
use App\Models\BudgetAllocation;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PaymentAccount;
use App\Models\PaymentAccountTransaction;
use App\Services\PaymentAccountService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ExpenseController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'expense';

    public function __construct(private PaymentAccountService $accounts)
    {
    }

    public function index(Request $request)
    {
        $categories = ExpenseCategory::where('status', true)->orderBy('title')->get();

        $from = $request->input('from_date', now()->subYear()->toDateString());
        $to = $request->input('to_date', now()->toDateString());

        $query = Expense::with(['category', 'paymentAccount'])
            ->whereBetween('date', [$from, $to]);

        if ($title = $request->input('title')) {
            $query->where('title', 'like', "%{$title}%");
        }
        if ($categoryId = $request->input('category_id')) {
            $query->where('category_id', $categoryId);
        }

        $expenses = $query->orderByDesc('id')->paginate(25)->withQueryString();
        $total = (clone $query)->sum('amount');

        return view('admin.account.expense.index', compact('expenses', 'categories', 'from', 'to', 'total'));
    }

    public function create()
    {
        $categories = ExpenseCategory::where('status', true)->orderBy('title')->get();
        $accounts = PaymentAccount::active()->orderBy('title')->get();
        $methods = IncomeController::METHODS;
        $budgets = Budget::where('status', 'active')->orderBy('title')->get();

        return view('admin.account.expense.create', compact('categories', 'accounts', 'methods', 'budgets'));
    }

    public function store(Request $request)
    {
        $data = $this->validateExpense($request);

        $this->normalizeBudget($data);

        try {
            DB::transaction(function () use ($request, $data) {
                $this->assertAllocationFunds($data['budget_allocation_id'] ?? null, $data['amount']);

                $expense = new Expense($data);
                $expense->status = true;
                $expense->created_by = auth()->id();
                if ($request->hasFile('attach')) {
                    $expense->attach = $request->file('attach')->store('accounts/expense', 'local');
                }
                $expense->save();

                $this->linkToAccount($expense);
                $this->recompute($expense->budget_id, $expense->budget_allocation_id);

            });
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.account.expense.index')
            ->with('success', __('Expense recorded successfully.'));
    }

    public function edit(Expense $expense)
    {
        $categories = ExpenseCategory::where('status', true)->orderBy('title')->get();
        $accounts = PaymentAccount::active()->orderBy('title')->get();
        $methods = IncomeController::METHODS;
        $budgets = Budget::where('status', 'active')->orderBy('title')->get();
        $allocations = $expense->budget_id
            ? BudgetAllocation::with('expenseCategory')->where('budget_id', $expense->budget_id)->get()
            : collect();

        return view('admin.account.expense.edit', compact('expense', 'categories', 'accounts', 'methods', 'budgets', 'allocations'));
    }

    public function update(Request $request, Expense $expense)
    {
        $data = $this->validateExpense($request);
        $this->normalizeBudget($data);
        $old = $expense->toArray();
        $oldBudgetId = $expense->budget_id;
        $oldAllocationId = $expense->budget_allocation_id;

        try {
            DB::transaction(function () use ($request, $expense, $data, $oldBudgetId, $oldAllocationId) {
                $this->assertAllocationFunds($data['budget_allocation_id'] ?? null, $data['amount'], $expense->id);
                $this->accounts->reverseFor(PaymentAccountTransaction::REF_EXPENSE, $expense->id);

                if ($request->hasFile('attach')) {
                    if ($expense->attach) {
                        Storage::disk('public')->delete($expense->attach);
                    }
                    $data['attach'] = $request->file('attach')->store('accounts/expense', 'local');
                }

                $data['updated_by'] = auth()->id();
                $expense->update($data);

                $this->linkToAccount($expense);

                // Recompute both the new and the old budget/allocation (handles re-linking).
                $this->recompute($expense->budget_id, $expense->budget_allocation_id);
                $this->recompute($oldBudgetId, $oldAllocationId);
            });
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }


        return redirect()->route('admin.account.expense.index')
            ->with('success', __('Expense updated successfully.'));
    }

    public function destroy(Expense $expense)
    {
        $old = $expense->toArray();
        $budgetId = $expense->budget_id;
        $allocationId = $expense->budget_allocation_id;

        DB::transaction(function () use ($expense, $budgetId, $allocationId) {
            $this->accounts->reverseFor(PaymentAccountTransaction::REF_EXPENSE, $expense->id);
            if ($expense->attach) {
                Storage::disk('public')->delete($expense->attach);
            }
            $expense->delete();

            // Recompute after delete (source-of-truth, no drift).
            $this->recompute($budgetId, $allocationId);
        });


        return redirect()->route('admin.account.expense.index')
            ->with('success', __('Expense deleted successfully.'));
    }

    private function validateExpense(Request $request): array
    {
        return $request->validate([
            'category_id'        => ['required', 'exists:expense_categories,id'],
            'title'              => ['required', 'string', 'max:191'],
            'invoice_id'         => ['nullable', 'string', 'max:191'],
            'amount'             => ['required', 'numeric', 'min:0.01'],
            'date'               => ['required', 'date', 'before_or_equal:today'],
            'reference'          => ['nullable', 'string', 'max:191'],
            'payment_method'     => ['nullable', 'string', 'max:191'],
            'payment_account_id' => ['nullable', 'exists:payment_accounts,id'],
            'budget_id'          => ['nullable', 'exists:budgets,id'],
            'budget_allocation_id' => ['nullable', 'exists:budget_allocations,id'],
            'note'               => ['nullable', 'string'],
            'attach'             => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx,csv,zip', 'max:20480'],
        ]);
    }

    /** If an allocation is chosen, derive its parent budget so the two stay consistent. */
    private function normalizeBudget(array &$data): void
    {
        if (! empty($data['budget_allocation_id'])) {
            $alloc = BudgetAllocation::find($data['budget_allocation_id']);
            if ($alloc) {
                $data['budget_id'] = $alloc->budget_id;
            }
        }
    }

    /** Guard: an expense cannot exceed its allocation's remaining funds. */
    private function assertAllocationFunds(?int $allocationId, $amount, ?int $excludeExpenseId = null): void
    {
        if (! $allocationId) {
            return;
        }

        $alloc = BudgetAllocation::findOrFail($allocationId);
        $spentExcluding = $alloc->expenses()
            ->where('approval_status', '!=', 'rejected')
            ->when($excludeExpenseId, fn ($q) => $q->where('id', '!=', $excludeExpenseId))
            ->sum('amount');
        $available = bcsub((string) $alloc->allocated_amount, (string) $spentExcluding, 2);

        if (bccomp((string) $amount, $available, 2) > 0) {
            throw new RuntimeException(__('Expense exceeds the allocation\'s remaining funds (:amt).', ['amt' => number_format((float) $available, 2)]));
        }
    }

    /** Recompute spend on the affected allocation (cascades to its budget) and/or budget. */
    private function recompute(?int $budgetId, ?int $allocationId): void
    {
        if ($allocationId && $alloc = BudgetAllocation::find($allocationId)) {
            $alloc->updateSpentAmount();
        }
        if ($budgetId && $budget = Budget::find($budgetId)) {
            $budget->updateSpentAmount();
        }
    }

    /**
     * Debit the chosen payment account for this expense (if any). Throws when
     * the account has insufficient funds, rolling back the caller's transaction.
     */
    private function linkToAccount(Expense $expense): void
    {
        if (! $expense->payment_account_id) {
            return;
        }

        $account = PaymentAccount::findOrFail($expense->payment_account_id);

        $this->accounts->debit($account, $expense->amount, [
            'transaction_date'  => $expense->date,
            'title'             => 'Expense - ' . $expense->title,
            'reference_type'    => PaymentAccountTransaction::REF_EXPENSE,
            'reference_id'      => $expense->id,
            'payment_method'    => $expense->payment_method,
            'payment_reference' => $expense->reference,
        ]);
    }
}
