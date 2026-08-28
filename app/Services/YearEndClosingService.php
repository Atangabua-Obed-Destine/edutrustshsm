<?php

namespace App\Services;

use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Closes a fiscal year: zeroes the profit-and-loss accounts into equity.
 *
 * Closing a year previously just flipped a flag, so Class 6 and 7 balances ran
 * forever and the current year's result was never separated from prior years'.
 * The balance sheet compensated by folding cumulative revenue-minus-expenses
 * into equity on the fly, which balances but cannot distinguish this year's
 * result from retained earnings.
 *
 * The closing entry, in OHADA terms:
 *   DR each revenue account (class 7) by its credit balance
 *   CR income summary       by total revenue
 *   CR each expense account (class 6) by its debit balance
 *   DR income summary       by total expenses
 *   then income summary -> retained earnings for the net result.
 *
 * All of it is ONE balanced journal entry, posted through JournalEntry::post()
 * like any other, so the usual guards apply.
 */
class YearEndClosingService
{
    /** OHADA account codes this relies on, in preference order. */
    private const INCOME_SUMMARY = ['120', '12'];
    private const RETAINED_EARNINGS = ['110', '11'];

    /**
     * What closing the year would do, without doing it.
     *
     * @return array{revenue: float, expenses: float, net: float, lines: Collection}
     */
    public function preview(FiscalYear $year): array
    {
        $revenue = $this->balancesFor($year, 7);
        $expenses = $this->balancesFor($year, 6);

        return [
            'revenue' => round($revenue->sum('balance'), 2),
            'expenses' => round($expenses->sum('balance'), 2),
            'net' => round($revenue->sum('balance') - $expenses->sum('balance'), 2),
            'lines' => $revenue->concat($expenses),
        ];
    }

    /**
     * Generate and post the closing entry, then mark the year closed.
     */
    public function close(FiscalYear $year): JournalEntry
    {
        $this->assertClosable($year);

        $summary = $this->requireAccount(self::INCOME_SUMMARY, __('Income Summary'));
        $retained = $this->requireAccount(self::RETAINED_EARNINGS, __('Retained Earnings'));

        $revenue = $this->balancesFor($year, 7);
        $expenses = $this->balancesFor($year, 6);

        if ($revenue->isEmpty() && $expenses->isEmpty()) {
            throw new RuntimeException(__('There is nothing to close: no revenue or expense was posted in this year.'));
        }

        return DB::transaction(function () use ($year, $revenue, $expenses, $summary, $retained) {
            $entry = JournalEntry::create([
                'branch_id' => $year->branch_id,
                'entry_number' => JournalEntry::generateEntryNumber(),
                'entry_date' => $year->end_date,
                'fiscal_year_id' => $year->id,
                // Deliberately left unattached to a period: the closing entry
                // belongs to the year as a whole, and every period is already
                // closed by the time we get here.
                'accounting_period_id' => null,
                'journal_type' => 'closing',
                'description' => __('Year-end closing :year', ['year' => $year->name]),
                'is_system_generated' => true,
            ]);

            $line = 1;
            $totalRevenue = 0.0;
            $totalExpenses = 0.0;

            // Revenue accounts carry credit balances; debit them flat.
            foreach ($revenue as $row) {
                $entry->lines()->create([
                    'account_id' => $row->account->id,
                    'line_number' => $line++,
                    'debit' => $row->balance,
                    'credit' => 0,
                    'description' => __('Closing revenue'),
                ]);
                $totalRevenue += $row->balance;
            }

            // Expense accounts carry debit balances; credit them flat.
            foreach ($expenses as $row) {
                $entry->lines()->create([
                    'account_id' => $row->account->id,
                    'line_number' => $line++,
                    'debit' => 0,
                    'credit' => $row->balance,
                    'description' => __('Closing expenses'),
                ]);
                $totalExpenses += $row->balance;
            }

            $net = round($totalRevenue - $totalExpenses, 2);

            // Income summary absorbs both sides, then hands the net result to
            // retained earnings. Both legs are written so the account tells the
            // story, and they cancel to zero.
            if ($totalRevenue > 0) {
                $entry->lines()->create([
                    'account_id' => $summary->id, 'line_number' => $line++,
                    'debit' => 0, 'credit' => round($totalRevenue, 2),
                    'description' => __('Revenue to income summary'),
                ]);
            }

            if ($totalExpenses > 0) {
                $entry->lines()->create([
                    'account_id' => $summary->id, 'line_number' => $line++,
                    'debit' => round($totalExpenses, 2), 'credit' => 0,
                    'description' => __('Expenses to income summary'),
                ]);
            }

            if (abs($net) >= 0.01) {
                // A profit debits the summary and credits retained earnings; a
                // loss does the reverse.
                $entry->lines()->create([
                    'account_id' => $summary->id, 'line_number' => $line++,
                    'debit' => $net > 0 ? $net : 0,
                    'credit' => $net < 0 ? abs($net) : 0,
                    'description' => __('Net result to retained earnings'),
                ]);
                $entry->lines()->create([
                    'account_id' => $retained->id, 'line_number' => $line++,
                    'debit' => $net < 0 ? abs($net) : 0,
                    'credit' => $net > 0 ? $net : 0,
                    'description' => __('Net result for :year', ['year' => $year->name]),
                ]);
            }

            // post() refuses an unbalanced entry, which is the real guarantee
            // that the arithmetic above is right.
            $entry->post();

            $year->update(['is_closed' => true, 'is_active' => false]);

            return $entry->fresh('lines');
        });
    }

    /**
     * Undo a closing: reverse the entry and reopen the year.
     */
    public function reopen(FiscalYear $year): void
    {
        $entry = JournalEntry::where('fiscal_year_id', $year->id)
            ->where('journal_type', 'closing')
            ->where('is_posted', true)
            ->where('is_reversed', false)
            ->latest('id')
            ->first();

        if (! $entry) {
            throw new RuntimeException(__('No posted closing entry was found for this year.'));
        }

        DB::transaction(function () use ($entry, $year) {
            $entry->unpost();
            $entry->update(['is_reversed' => true]);
            $year->update(['is_closed' => false]);
        });
    }

    private function assertClosable(FiscalYear $year): void
    {
        if ($year->is_closed) {
            throw new RuntimeException(__('This fiscal year is already closed.'));
        }

        $openPeriods = AccountingPeriod::where('fiscal_year_id', $year->id)
            ->where('is_closed', false)->count();

        if ($openPeriods > 0) {
            throw new RuntimeException(__('All periods must be closed before closing the year.'));
        }

        $unposted = JournalEntry::where('fiscal_year_id', $year->id)
            ->where('is_posted', false)->count();

        if ($unposted > 0) {
            throw new RuntimeException(__('All entries in the year must be posted before closing it.'));
        }
    }

    /**
     * Posted balances for one OHADA class within the year.
     *
     * @return Collection<int, object{account: ChartOfAccount, balance: float}>
     */
    private function balancesFor(FiscalYear $year, int $class): Collection
    {
        return ChartOfAccount::postable()
            ->where('class_number', $class)
            ->orderBy('account_code')
            ->get()
            ->map(function (ChartOfAccount $account) use ($year) {
                $balance = (float) $account->postedBalance(
                    $year->start_date->toDateString(),
                    $year->end_date->toDateString()
                );

                return (object) ['account' => $account, 'balance' => round($balance, 2)];
            })
            ->filter(fn ($row) => abs($row->balance) >= 0.01)
            ->values();
    }

    /** @param array<int, string> $codes */
    private function requireAccount(array $codes, string $label): ChartOfAccount
    {
        foreach ($codes as $code) {
            $account = ChartOfAccount::where('account_code', $code)->first();

            if ($account) {
                return $account;
            }
        }

        throw new RuntimeException(__('The :account account is missing from the chart of accounts.', ['account' => $label]));
    }
}
