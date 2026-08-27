<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Payment;
use App\Models\Payroll;
use App\Models\PaymentAccount;
use App\Models\PaymentAccountTransaction;
use App\Services\PaymentAccountService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PaymentAccountReportController extends Controller
{
    public function __construct(private PaymentAccountService $accounts)
    {
    }

    /** Cross-account cash flow with filters; summary computed before pagination. */
    public function cashflow(Request $request)
    {
        $accounts = PaymentAccount::orderBy('title')->get();

        $query = PaymentAccountTransaction::with('paymentAccount')
            ->when($request->account_id, fn ($q, $v) => $q->where('payment_account_id', $v))
            ->when($request->date_from, fn ($q, $v) => $q->whereDate('transaction_date', '>=', $v))
            ->when($request->date_to, fn ($q, $v) => $q->whereDate('transaction_date', '<=', $v))
            ->when($request->transaction_type, fn ($q, $v) => $q->where('transaction_type', $v))
            ->when($request->payment_method, fn ($q, $v) => $q->where('payment_method', $v));

        $summaryBase = (clone $query);
        $totalCredit = (clone $summaryBase)->where('transaction_type', 'credit')->sum('amount');
        $totalDebit = (clone $summaryBase)->where('transaction_type', 'debit')->sum('amount');
        $summary = [
            'total_credit' => $totalCredit,
            'total_debit' => $totalDebit,
            'net_flow' => bcsub((string) $totalCredit, (string) $totalDebit, 2),
            'total_balance' => PaymentAccount::sum('current_balance'),
        ];

        $transactions = $query->orderByDesc('transaction_date')->orderByDesc('id')
            ->paginate(50)->withQueryString();

        return view('admin.payment-account.reports.cashflow', compact('accounts', 'transactions', 'summary'));
    }

    /** Single-account statement for a date range. */
    public function statement(Request $request, PaymentAccount $payment_account)
    {
        $from = $request->input('date_from', now()->startOfMonth()->toDateString());
        $to = $request->input('date_to', now()->toDateString());

        $opening = PaymentAccountTransaction::where('payment_account_id', $payment_account->id)
            ->whereDate('transaction_date', '<', $from)
            ->orderByDesc('transaction_date')->orderByDesc('id')
            ->value('balance_after') ?? $payment_account->opening_balance;

        $transactions = PaymentAccountTransaction::where('payment_account_id', $payment_account->id)
            ->whereBetween('transaction_date', [$from, $to])
            ->orderBy('transaction_date')->orderBy('id')
            ->get();

        $periodCredit = $transactions->where('transaction_type', 'credit')->sum('amount');
        $periodDebit = $transactions->where('transaction_type', 'debit')->sum('amount');

        return view('admin.payment-account.reports.statement', [
            'account' => $payment_account,
            'transactions' => $transactions,
            'opening' => $opening,
            'closing' => $payment_account->current_balance,
            'periodCredit' => $periodCredit,
            'periodDebit' => $periodDebit,
            'from' => $from,
            'to' => $to,
        ]);
    }

    /** Per-account credit/debit/net/count for a period + grand totals. */
    public function summary(Request $request)
    {
        $from = $request->input('date_from', now()->startOfYear()->toDateString());
        $to = $request->input('date_to', now()->toDateString());

        $rows = PaymentAccount::with('accountType')->orderBy('title')->get()->map(function ($acc) use ($from, $to) {
            $txns = $acc->transactions()->whereBetween('transaction_date', [$from, $to])->get();
            $credit = $txns->where('transaction_type', 'credit')->sum('amount');
            $debit = $txns->where('transaction_type', 'debit')->sum('amount');
            return (object) [
                'account' => $acc,
                'credit' => $credit,
                'debit' => $debit,
                'net' => bcsub((string) $credit, (string) $debit, 2),
                'count' => $txns->count(),
                'balance' => $acc->current_balance,
            ];
        });

        return view('admin.payment-account.reports.summary', compact('rows', 'from', 'to'));
    }

    /** Operational records not yet linked to any payment account. */
    public function unlinked(Request $request)
    {
        $accounts = PaymentAccount::active()->orderBy('title')->get();

        $incomes = Income::whereNull('payment_account_id')->get()->map(fn ($r) => $this->row(
            PaymentAccountTransaction::REF_INCOME, $r->id, $r->date, $r->amount, $r->title, 'credit', $r->payment_method, $r->reference
        ));
        $expenses = Expense::whereNull('payment_account_id')->get()->map(fn ($r) => $this->row(
            PaymentAccountTransaction::REF_EXPENSE, $r->id, $r->date, $r->amount, $r->title, 'debit', $r->payment_method, $r->reference
        ));
        $fees = Payment::whereNull('payment_account_id')->get()->map(fn ($r) => $this->row(
            PaymentAccountTransaction::REF_FEE_PAYMENT, $r->id, $r->payment_date, $r->amount,
            'Fee Receipt ' . $r->receipt_number, 'credit', $r->payment_method, $r->transaction_ref
        ));

        // Paid payrolls whose cash outflow was never attached to an account.
        $payrolls = Payroll::where('status', Payroll::STATUS_PAID)
            ->whereNull('payment_account_id')->with('user')->get()
            ->map(fn ($r) => $this->row(
                PaymentAccountTransaction::REF_PAYROLL, $r->id, $r->pay_date, $r->net_salary,
                __('Salary').' '.$r->salary_month.' - '.($r->user?->full_name ?? ''),
                'debit', $r->payment_method, null
            ));

        $merged = $incomes->concat($expenses)->concat($fees)->concat($payrolls)
            ->sortByDesc('date')->values();

        $page = (int) $request->input('page', 1);
        $perPage = 25;
        $items = $merged->forPage($page, $perPage)->values();
        $records = new LengthAwarePaginator($items, $merged->count(), $perPage, $page, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);

        return view('admin.payment-account.reports.unlinked', compact('records', 'accounts'));
    }

    /** Deferred linking: assign an account to an unlinked record. */
    public function link(Request $request)
    {
        $validated = $request->validate([
            'record_type'        => ['required', 'in:income,expense,fee_payment'],
            'record_id'          => ['required', 'integer'],
            'payment_account_id' => ['required', 'exists:payment_accounts,id'],
        ]);

        try {
            DB::transaction(function () use ($validated) {
                $account = PaymentAccount::findOrFail($validated['payment_account_id']);
                [$record, $amount, $date, $title, $direction, $method, $ref] = $this->resolveRecord(
                    $validated['record_type'], $validated['record_id']
                );

                if ($record->payment_account_id) {
                    throw new RuntimeException(__('This record is already linked to an account.'));
                }

                $meta = [
                    'transaction_date' => $date,
                    'title'            => $title,
                    'reference_type'   => $validated['record_type'],
                    'reference_id'     => $record->id,
                    'payment_method'   => $method,
                    'payment_reference' => $ref,
                ];

                if ($direction === 'credit') {
                    $this->accounts->credit($account, $amount, $meta);
                } else {
                    $this->accounts->debit($account, $amount, $meta);
                }

                $record->update(['payment_account_id' => $account->id]);

                AuditLog::log('linked', get_class($record), $record->id, null, [
                    'payment_account_id' => $account->id,
                ]);
            });
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Transaction linked to account successfully.'));
    }

    private function resolveRecord(string $type, int $id): array
    {
        return match ($type) {
            PaymentAccountTransaction::REF_INCOME => (function () use ($id) {
                $r = Income::findOrFail($id);
                return [$r, $r->amount, $r->date, 'Income - ' . $r->title, 'credit', $r->payment_method, $r->reference];
            })(),
            PaymentAccountTransaction::REF_EXPENSE => (function () use ($id) {
                $r = Expense::findOrFail($id);
                return [$r, $r->amount, $r->date, 'Expense - ' . $r->title, 'debit', $r->payment_method, $r->reference];
            })(),
            PaymentAccountTransaction::REF_FEE_PAYMENT => (function () use ($id) {
                $r = Payment::findOrFail($id);
                return [$r, $r->amount, $r->payment_date, 'Fee Receipt ' . $r->receipt_number, 'credit', $r->payment_method, $r->transaction_ref];
            })(),
        };
    }

    private function row(string $type, int $id, $date, $amount, $title, string $direction, $method, $ref): array
    {
        return [
            'record_type' => $type,
            'record_id' => $id,
            'date' => $date,
            'amount' => $amount,
            'title' => $title,
            'direction' => $direction,
            'method' => $method,
            'reference' => $ref,
        ];
    }
}
