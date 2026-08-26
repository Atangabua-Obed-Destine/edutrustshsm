<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use App\Models\JournalEntryLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * All reports are recomputed by summing POSTED journal lines — never the cached
 * chart_of_accounts.current_balance (guide gotcha #3).
 */
class GeneralLedgerController extends Controller
{
    public function index()
    {
        $accounts = ChartOfAccount::postable()->orderBy('account_code')->get();
        return view('admin.accounting.reports.general-ledger', compact('accounts'));
    }

    /** Single-account ledger: opening balance + running balance through the period. */
    public function account(Request $request)
    {
        $account = ChartOfAccount::findOrFail($request->account_id);
        $from = $request->start_date;
        $to = $request->end_date;

        // Opening = signed sum of posted lines strictly before $from.
        $opening = '0';
        if ($from) {
            $opening = $this->signedSum($account, null, \Illuminate\Support\Carbon::parse($from)->subDay()->toDateString());
        }

        $lines = JournalEntryLine::with('journalEntry')
            ->where('account_id', $account->id)
            ->whereHas('journalEntry', function ($q) use ($from, $to) {
                $q->where('is_posted', true);
                if ($from) $q->whereDate('entry_date', '>=', $from);
                if ($to) $q->whereDate('entry_date', '<=', $to);
            })
            ->get()
            ->sortBy(fn ($l) => [$l->journalEntry->entry_date->toDateString(), $l->id])
            ->values();

        $running = $opening;
        $rows = $lines->map(function ($l) use (&$running, $account) {
            $delta = $account->normal_balance === 'debit'
                ? bcsub((string) $l->debit, (string) $l->credit, 2)
                : bcsub((string) $l->credit, (string) $l->debit, 2);
            $running = bcadd($running, $delta, 2);
            return (object) [
                'date' => $l->journalEntry->entry_date,
                'entry_number' => $l->journalEntry->entry_number,
                'description' => $l->description ?: $l->journalEntry->description,
                'debit' => $l->debit,
                'credit' => $l->credit,
                'balance' => $running,
            ];
        });

        return view('admin.accounting.reports.account-ledger', compact('account', 'rows', 'opening', 'from', 'to'));
    }

    /** Trial balance: per account, opening + period debit/credit; always balances. */
    public function trialBalance(Request $request)
    {
        $to = $request->end_date;
        $from = $request->start_date;

        $accounts = ChartOfAccount::postable()->orderBy('account_code')->get();

        $rows = $accounts->map(function ($a) use ($from, $to) {
            $periodDebit = $this->rawSum($a->id, 'debit', $from, $to);
            $periodCredit = $this->rawSum($a->id, 'credit', $from, $to);
            $closing = $a->postedBalance(null, $to);
            return (object) [
                'account' => $a,
                'period_debit' => $periodDebit,
                'period_credit' => $periodCredit,
                'closing_debit' => $a->normal_balance === 'debit' ? max((float) $closing, 0) : 0,
                'closing_credit' => $a->normal_balance === 'credit' ? max((float) $closing, 0) : 0,
            ];
        })->filter(fn ($r) => $r->period_debit != 0 || $r->period_credit != 0 || $r->closing_debit != 0 || $r->closing_credit != 0)->values();

        $totals = [
            'debit' => $rows->sum('period_debit'),
            'credit' => $rows->sum('period_credit'),
            'closing_debit' => $rows->sum('closing_debit'),
            'closing_credit' => $rows->sum('closing_credit'),
        ];

        return view('admin.accounting.reports.trial-balance', compact('rows', 'totals', 'from', 'to'));
    }

    /** Balance sheet: Assets vs Liabilities + Equity, from posted lines. */
    public function balanceSheet(Request $request)
    {
        $to = $request->end_date ?? now()->toDateString();
        $accounts = ChartOfAccount::postable()->orderBy('account_code')->get();

        $assets = collect();
        $liabilities = collect();
        $equity = collect();

        foreach ($accounts as $a) {
            $bal = (float) $a->postedBalance(null, $to);
            if (abs($bal) < 0.01) continue;
            $row = (object) ['account' => $a, 'balance' => abs($bal)];
            if (in_array($a->class_number, [2, 3, 5]) || ($a->class_number == 4 && $a->normal_balance === 'debit')) {
                $assets->push($row);
            } elseif ($a->class_number == 1) {
                $equity->push($row);
            } elseif ($a->class_number == 4 && $a->normal_balance === 'credit') {
                $liabilities->push($row);
            }
        }

        // Net result (Class 7 - Class 6) folds into equity until year-end closing.
        $netResult = (float) $this->classBalance(7, $to) - (float) $this->classBalance(6, $to);

        $totalAssets = $assets->sum('balance');
        $totalLiab = $liabilities->sum('balance');
        $totalEquity = $equity->sum('balance') + $netResult;

        return view('admin.accounting.reports.balance-sheet', compact('assets', 'liabilities', 'equity', 'netResult', 'totalAssets', 'totalLiab', 'totalEquity', 'to'));
    }

    /** Income statement (P&L) from posted journal lines (single source). */
    public function incomeStatement(Request $request)
    {
        $from = $request->start_date;
        $to = $request->end_date ?? now()->toDateString();
        $accounts = ChartOfAccount::postable()->orderBy('account_code')->get();

        $revenue = collect();
        $expense = collect();
        foreach ($accounts as $a) {
            if ($a->class_number == 7) {
                $bal = (float) $a->postedBalance($from, $to);
                if (abs($bal) >= 0.01) $revenue->push((object) ['account' => $a, 'balance' => $bal]);
            } elseif ($a->class_number == 6) {
                $bal = (float) $a->postedBalance($from, $to);
                if (abs($bal) >= 0.01) $expense->push((object) ['account' => $a, 'balance' => $bal]);
            }
        }

        $totalRevenue = $revenue->sum('balance');
        $totalExpense = $expense->sum('balance');
        $netResult = $totalRevenue - $totalExpense;

        return view('admin.accounting.reports.income-statement', compact('revenue', 'expense', 'totalRevenue', 'totalExpense', 'netResult', 'from', 'to'));
    }

    // ── helpers ──
    private function signedSum(ChartOfAccount $a, ?string $from, ?string $to): string
    {
        return $a->postedBalance($from, $to);
    }

    private function rawSum(int $accountId, string $col, ?string $from, ?string $to): float
    {
        return (float) JournalEntryLine::where('account_id', $accountId)
            ->whereHas('journalEntry', function ($q) use ($from, $to) {
                $q->where('is_posted', true);
                if ($from) $q->whereDate('entry_date', '>=', $from);
                if ($to) $q->whereDate('entry_date', '<=', $to);
            })->sum($col);
    }

    private function classBalance(int $class, ?string $to): string
    {
        $accounts = ChartOfAccount::postable()->where('class_number', $class)->get();
        $sum = '0';
        foreach ($accounts as $a) {
            $sum = bcadd($sum, (string) $a->postedBalance(null, $to), 2);
        }
        return $sum;
    }
}
