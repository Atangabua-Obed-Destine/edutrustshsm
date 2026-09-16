<?php

namespace App\Services;

use App\Models\AccountingPeriod;
use App\Models\DepreciationSchedule;
use App\Models\Expense;
use App\Models\FiscalYear;
use App\Models\Income;
use App\Models\JournalEntry;
use App\Models\PaymentAllocation;
use App\Models\RecurringJournalEntry;
use App\Models\TransactionMapping;
use App\Models\YearEndClosing;
use Illuminate\Support\Facades\DB;

/**
 * What has to be true before a fiscal year may be closed.
 *
 * The reference system gates closing behind a checklist. Some of what it asks
 * a person to tick can simply be checked here, so it is: every period closed,
 * nothing left unposted, the year's postings in balance, no depreciation or
 * recurring entry left due, and nothing recorded in the year that never reached
 * the ledger. Closing with any of those outstanding would lock a year whose
 * result is already wrong.
 *
 * The rest — whether the bank statements were reconciled, whether the cash was
 * counted — only a person can know, so those are confirmations, each recorded
 * with who made it and when.
 */
class YearEndChecklistService
{
    /** Confirmations a person has to make, with their labels. */
    public const MANUAL = [
        'bank_reconciled' => 'Bank statements reconciled to the bank accounts',
        'cash_counted' => 'Cash on hand counted and agreed to the cash accounts',
        'payroll_complete' => 'Payroll for the final month paid and posted',
        'receivables_reviewed' => 'Outstanding fees and receivables reviewed',
    ];

    /**
     * The checks the system can make itself.
     *
     * @return array<int, array{key: string, label: string, passed: bool, detail: ?string}>
     */
    public function automatic(FiscalYear $year): array
    {
        $from = $year->start_date->toDateString();
        $to = $year->end_date->toDateString();

        $openPeriods = AccountingPeriod::where('fiscal_year_id', $year->id)->where('is_closed', false)->count();
        $unposted = JournalEntry::where('fiscal_year_id', $year->id)->where('is_posted', false)->count();

        $totals = DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('e.fiscal_year_id', $year->id)
            ->where('e.is_posted', true)
            ->selectRaw('COALESCE(SUM(l.debit), 0) as debit, COALESCE(SUM(l.credit), 0) as credit')
            ->first();
        $difference = round((float) $totals->debit - (float) $totals->credit, 2);

        $depreciationDue = DepreciationSchedule::unposted()
            ->whereDate('period_date', '<=', $to)
            ->whereHas('asset', fn ($q) => $q->where('status', 'active'))
            ->count();

        $recurringDue = RecurringJournalEntry::due($to)->count();
        $unmapped = $this->unmappedIn($from, $to);

        return [
            $this->check('periods_closed', __('Every accounting period is closed'), $openPeriods === 0,
                trans_choice(':count period is still open.|:count periods are still open.', $openPeriods, ['count' => $openPeriods])),
            $this->check('entries_posted', __('Every journal entry in the year is posted'), $unposted === 0,
                trans_choice(':count entry is still a draft.|:count entries are still drafts.', $unposted, ['count' => $unposted])),
            $this->check('balanced', __('The year\'s postings are in balance'), abs($difference) < 0.01,
                __('Debits and credits differ by :amount.', ['amount' => number_format($difference, 2)])),
            $this->check('depreciation_posted', __('No depreciation is left due'), $depreciationDue === 0,
                trans_choice(':count depreciation period has come due and is not posted.|:count depreciation periods have come due and are not posted.', $depreciationDue, ['count' => $depreciationDue])),
            $this->check('recurring_generated', __('No recurring entry is left due'), $recurringDue === 0,
                trans_choice(':count recurring entry is due and not generated.|:count recurring entries are due and not generated.', $recurringDue, ['count' => $recurringDue])),
            $this->check('transactions_posted', __('Everything recorded in the year reached the ledger'), $unmapped === 0,
                trans_choice(':count income, expense or fee payment has no ledger posting.|:count incomes, expenses or fee payments have no ledger posting.', $unmapped, ['count' => $unmapped])),
        ];
    }

    /**
     * The confirmations, with who made each one.
     *
     * @return array<int, array{key: string, label: string, confirmed: bool, by: ?int, at: ?string}>
     */
    public function manual(?YearEndClosing $closing): array
    {
        $made = $closing?->confirmations ?? [];

        return collect(self::MANUAL)->map(fn ($label, $key) => [
            'key' => $key,
            'label' => __($label),
            'confirmed' => isset($made[$key]),
            'by' => $made[$key]['by'] ?? null,
            'at' => $made[$key]['at'] ?? null,
        ])->values()->all();
    }

    /**
     * What still stands between the year and closing, as sentences.
     *
     * @return array<int, string>
     */
    public function outstanding(FiscalYear $year, ?YearEndClosing $closing): array
    {
        $missing = [];

        foreach ($this->automatic($year) as $check) {
            if (! $check['passed']) {
                $missing[] = $check['detail'];
            }
        }

        foreach ($this->manual($closing) as $item) {
            if (! $item['confirmed']) {
                $missing[] = __('Not yet confirmed: :item.', ['item' => $item['label']]);
            }
        }

        return $missing;
    }

    /** @return array{key: string, label: string, passed: bool, detail: ?string} */
    private function check(string $key, string $label, bool $passed, string $detail): array
    {
        return ['key' => $key, 'label' => $label, 'passed' => $passed, 'detail' => $passed ? null : $detail];
    }

    /** Facts dated in the year with no active ledger posting. */
    private function unmappedIn(string $from, string $to): int
    {
        $mapped = fn (string $type) => TransactionMapping::where('transaction_type', $type)
            ->where('status', 'active')->select('transaction_id');

        $incomes = Income::whereBetween('date', [$from, $to])->whereNotIn('id', $mapped('income'))->count();
        $expenses = Expense::whereBetween('date', [$from, $to])->whereNotIn('id', $mapped('expense'))->count();

        // Reversed receipts are no longer money and were never meant to post.
        $allocations = PaymentAllocation::whereHas('payment', fn ($q) => $q
            ->where('verification_status', 'verified')
            ->whereBetween('payment_date', [$from, $to])
            ->where('payment_method', '!=', 'student_credit'))
            ->whereNotIn('id', $mapped('fee_payment'))
            ->count();

        return $incomes + $expenses + $allocations;
    }
}
