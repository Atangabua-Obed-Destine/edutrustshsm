<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\Income;
use App\Models\IncomeCategory;
use App\Models\PaymentAccount;
use App\Models\PaymentAccountTransaction;
use App\Services\PaymentAccountService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class IncomeController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'income';

    /** Payment method options shared across finance forms. */
    public const METHODS = ['Cash', 'Bank', 'MTN Mobile Money', 'Orange Money', 'Cheque', 'Other'];

    public function __construct(private PaymentAccountService $accounts)
    {
    }

    public function index(Request $request)
    {
        $categories = IncomeCategory::where('status', true)->orderBy('title')->get();

        $from = $request->input('from_date', now()->subYear()->toDateString());
        $to = $request->input('to_date', now()->toDateString());

        $query = Income::with(['category', 'paymentAccount'])
            ->whereBetween('date', [$from, $to]);

        if ($title = $request->input('title')) {
            $query->where('title', 'like', "%{$title}%");
        }
        if ($categoryId = $request->input('category_id')) {
            $query->where('category_id', $categoryId);
        }

        $incomes = $query->orderByDesc('id')->paginate(25)->withQueryString();
        $total = (clone $query)->sum('amount');

        return view('admin.account.income.index', compact('incomes', 'categories', 'from', 'to', 'total'));
    }

    public function create()
    {
        $categories = IncomeCategory::where('status', true)->orderBy('title')->get();
        $accounts = PaymentAccount::active()->orderBy('title')->get();
        $methods = self::METHODS;

        return view('admin.account.income.create', compact('categories', 'accounts', 'methods'));
    }

    public function store(Request $request)
    {
        $data = $this->validateIncome($request);

        DB::transaction(function () use ($request, $data) {
            $income = new Income($data);
            $income->status = true;
            $income->created_by = auth()->id();
            if ($request->hasFile('attach')) {
                $income->attach = $request->file('attach')->store('accounts/income', 'local');
            }
            $income->save();

            $this->linkToAccount($income);

        });

        return redirect()->route('admin.account.income.index')
            ->with('success', __('Income recorded successfully.'));
    }

    public function edit(Income $income)
    {
        $categories = IncomeCategory::where('status', true)->orderBy('title')->get();
        $accounts = PaymentAccount::active()->orderBy('title')->get();
        $methods = self::METHODS;

        return view('admin.account.income.edit', compact('income', 'categories', 'accounts', 'methods'));
    }

    public function update(Request $request, Income $income)
    {
        $data = $this->validateIncome($request);
        $old = $income->toArray();

        DB::transaction(function () use ($request, $income, $data) {
            // Reverse any existing cash-ledger effect, then re-apply with new values (no drift).
            $this->accounts->reverseFor(PaymentAccountTransaction::REF_INCOME, $income->id);

            if ($request->hasFile('attach')) {
                if ($income->attach) {
                    Storage::disk('public')->delete($income->attach);
                }
                $data['attach'] = $request->file('attach')->store('accounts/income', 'local');
            }

            $data['updated_by'] = auth()->id();
            $income->update($data);

            $this->linkToAccount($income);
        });


        return redirect()->route('admin.account.income.index')
            ->with('success', __('Income updated successfully.'));
    }

    public function destroy(Income $income)
    {
        $old = $income->toArray();

        DB::transaction(function () use ($income) {
            $this->accounts->reverseFor(PaymentAccountTransaction::REF_INCOME, $income->id);
            if ($income->attach) {
                Storage::disk('public')->delete($income->attach);
            }
            $income->delete();
        });


        return redirect()->route('admin.account.income.index')
            ->with('success', __('Income deleted successfully.'));
    }

    private function validateIncome(Request $request): array
    {
        return $request->validate([
            'category_id'        => ['required', 'exists:income_categories,id'],
            'title'              => ['required', 'string', 'max:191'],
            'invoice_id'         => ['nullable', 'string', 'max:191'],
            'amount'             => ['required', 'numeric', 'min:0.01'],
            'date'               => ['required', 'date', 'before_or_equal:today'],
            'reference'          => ['nullable', 'string', 'max:191'],
            'payment_method'     => ['nullable', 'string', 'max:191'],
            'payment_account_id' => ['nullable', 'exists:payment_accounts,id'],
            'note'               => ['nullable', 'string'],
            'attach'             => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx,csv,zip', 'max:20480'],
        ]);
    }

    /**
     * Credit the chosen payment account for this income (if any), inside the
     * caller's DB transaction.
     */
    private function linkToAccount(Income $income): void
    {
        if (! $income->payment_account_id) {
            return;
        }

        $account = PaymentAccount::findOrFail($income->payment_account_id);

        $this->accounts->credit($account, $income->amount, [
            'transaction_date' => $income->date,
            'title'            => 'Income - ' . $income->title,
            'reference_type'   => PaymentAccountTransaction::REF_INCOME,
            'reference_id'     => $income->id,
            'payment_method'   => $income->payment_method,
            'payment_reference' => $income->reference,
        ]);
    }
}
