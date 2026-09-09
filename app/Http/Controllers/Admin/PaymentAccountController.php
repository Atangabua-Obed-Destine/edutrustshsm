<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\PaymentAccount;
use App\Models\PaymentAccountTransaction;
use App\Models\PaymentAccountType;
use App\Services\PaymentAccountService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class PaymentAccountController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'payment-account';

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('payment-account.view', ['accountBook']),
            static::can('payment-account.deposit', ['depositForm', 'deposit']),
            static::can('payment-account.withdraw', ['withdrawForm', 'withdraw']),
            static::can('payment-account.recompute', ['recompute']),
            static::can('payment-account.delete', ['destroyTransaction']),
        ];
    }

    public function __construct(private PaymentAccountService $accounts)
    {
    }

    public function index()
    {
        $accounts = PaymentAccount::with('accountType')->orderBy('title')->get();
        $totalBalance = $accounts->sum('current_balance');
        $unlinkedCount = $this->unlinkedCount();

        return view('admin.payment-account.index', compact('accounts', 'totalBalance', 'unlinkedCount'));
    }

    public function create()
    {
        $types = PaymentAccountType::where('status', true)->orderBy('title')->get();
        return view('admin.payment-account.create', compact('types'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'           => ['required', 'string', 'max:191'],
            'account_number'  => ['nullable', 'string', 'max:191'],
            'account_type_id' => ['required', 'exists:payment_account_types,id'],
            'opening_balance' => ['required', 'numeric', 'min:0'],
            'description'     => ['nullable', 'string'],
            'status'          => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($validated) {
            $account = PaymentAccount::create([
                'title'           => $validated['title'],
                'account_number'  => $validated['account_number'] ?? null,
                'account_type_id' => $validated['account_type_id'],
                'opening_balance' => $validated['opening_balance'],
                'current_balance' => 0,
                'description'     => $validated['description'] ?? null,
                'status'          => $validated['status'],
                'created_by'      => auth()->id(),
            ]);

            // Seed an opening-balance credit so the ledger reconciles from day one.
            if ((float) $validated['opening_balance'] > 0) {
                $this->accounts->credit($account, $validated['opening_balance'], [
                    'transaction_date' => now()->toDateString(),
                    'title'            => 'Opening Balance',
                    'reference_type'   => PaymentAccountTransaction::REF_DEPOSIT,
                ]);
            }

        });

        return redirect()->route('admin.payment-account.index')
            ->with('success', __('Payment account created successfully.'));
    }

    public function edit(PaymentAccount $payment_account)
    {
        $types = PaymentAccountType::where('status', true)->orderBy('title')->get();
        return view('admin.payment-account.edit', ['account' => $payment_account, 'types' => $types]);
    }

    public function update(Request $request, PaymentAccount $payment_account)
    {
        $validated = $request->validate([
            'title'           => ['required', 'string', 'max:191'],
            'account_number'  => ['nullable', 'string', 'max:191'],
            'account_type_id' => ['required', 'exists:payment_account_types,id'],
            'description'     => ['nullable', 'string'],
            'status'          => ['required', 'boolean'],
        ]);

        // Note: opening_balance/current_balance are not editable here (would desync the ledger).
        $old = $payment_account->toArray();
        $payment_account->update($validated + ['updated_by' => auth()->id()]);


        return redirect()->route('admin.payment-account.index')
            ->with('success', __('Payment account updated successfully.'));
    }

    public function destroy(PaymentAccount $payment_account)
    {
        if ($payment_account->transactions()->exists()) {
            return back()->with('error', __('Cannot delete an account that has transactions.'));
        }

        $old = $payment_account->toArray();
        $payment_account->delete();

        return redirect()->route('admin.payment-account.index')
            ->with('success', __('Payment account deleted successfully.'));
    }

    /** Account book — ledger for a single account. */
    public function accountBook(PaymentAccount $payment_account)
    {
        $transactions = $payment_account->transactions()->paginate(50);
        return view('admin.payment-account.account-book', ['account' => $payment_account, 'transactions' => $transactions]);
    }

    public function depositForm(PaymentAccount $payment_account)
    {
        return view('admin.payment-account.deposit', ['account' => $payment_account]);
    }

    public function deposit(Request $request, PaymentAccount $payment_account)
    {
        $validated = $this->validateMovement($request);

        DB::transaction(function () use ($request, $payment_account, $validated) {
            $this->accounts->credit($payment_account, $validated['amount'], [
                'transaction_date' => $validated['transaction_date'],
                'title'            => $validated['title'] ?? 'Deposit',
                'description'      => $validated['description'] ?? null,
                'reference_type'   => PaymentAccountTransaction::REF_DEPOSIT,
                'payment_method'   => $validated['payment_method'] ?? null,
                'payment_reference' => $validated['payment_reference'] ?? null,
                'attach'           => $request->hasFile('attach')
                    ? $request->file('attach')->store('accounts/transactions', 'local') : null,
            ]);
        });

        return redirect()->route('admin.payment-account.account-book', $payment_account)
            ->with('success', __('Deposit recorded successfully.'));
    }

    public function withdrawForm(PaymentAccount $payment_account)
    {
        return view('admin.payment-account.withdraw', ['account' => $payment_account]);
    }

    public function withdraw(Request $request, PaymentAccount $payment_account)
    {
        $validated = $this->validateMovement($request);

        try {
            DB::transaction(function () use ($request, $payment_account, $validated) {
                $this->accounts->debit($payment_account, $validated['amount'], [
                    'transaction_date' => $validated['transaction_date'],
                    'title'            => $validated['title'] ?? 'Withdrawal',
                    'description'      => $validated['description'] ?? null,
                    'reference_type'   => PaymentAccountTransaction::REF_WITHDRAWAL,
                    'payment_method'   => $validated['payment_method'] ?? null,
                    'payment_reference' => $validated['payment_reference'] ?? null,
                    'attach'           => $request->hasFile('attach')
                        ? $request->file('attach')->store('accounts/transactions', 'local') : null,
                ]);
            });
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.payment-account.account-book', $payment_account)
            ->with('success', __('Withdrawal recorded successfully.'));
    }

    /** Repair drift: recompute current_balance + balance_after from the ledger. */
    public function recompute(PaymentAccount $payment_account)
    {
        $new = $payment_account->recomputeBalance();
        return back()->with('success', __('Balance recomputed: ') . number_format($new, 2));
    }

    /** Delete a manual (unlinked) transaction; blocked for linked sources. */
    public function destroyTransaction(PaymentAccountTransaction $transaction)
    {
        if ($transaction->isLinked()) {
            return back()->with('error', __('This transaction is owned by its source record and cannot be deleted here.'));
        }

        DB::transaction(function () use ($transaction) {
            if ($transaction->attach) {
                Storage::disk('public')->delete($transaction->attach);
            }
            $this->accounts->reverseTransaction($transaction);
        });

        return back()->with('success', __('Transaction deleted and balance adjusted.'));
    }

    private function validateMovement(Request $request): array
    {
        return $request->validate([
            'amount'            => ['required', 'numeric', 'min:0.01'],
            'transaction_date'  => ['required', 'date', 'before_or_equal:today'],
            'title'             => ['nullable', 'string', 'max:191'],
            'description'       => ['nullable', 'string'],
            'payment_method'    => ['nullable', 'string', 'max:191'],
            'payment_reference' => ['nullable', 'string', 'max:191'],
            'attach'            => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:20480'],
        ]);
    }

    private function unlinkedCount(): int
    {
        return \App\Models\Income::whereNull('payment_account_id')->count()
            + \App\Models\Expense::whereNull('payment_account_id')->count()
            + \App\Models\Payment::whereNull('payment_account_id')->count()
            + \App\Models\Payroll::where('status', \App\Models\Payroll::STATUS_PAID)
                ->whereNull('payment_account_id')->count();
    }
}
