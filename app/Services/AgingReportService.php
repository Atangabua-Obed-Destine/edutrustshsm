<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\JournalEntryLine;
use App\Models\StudentFee;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Ageing analysis: how old is the money owed to us, and the money we owe.
 *
 * Two sources, deliberately:
 *
 *  - Receivables and payables age POSTED LEDGER lines on OHADA class 4
 *    (third parties), because that is what the balance sheet reports.
 *  - Student fees age the OPERATIONAL `student_fees` table instead, because it
 *    carries a real per-fee `due_date` and outstanding balance. Deriving that
 *    from the ledger would mean reconstructing per-student sub-ledgers the
 *    chart of accounts does not model.
 */
class AgingReportService
{
    /** Bucket upper bounds in days; the final bucket is open-ended. */
    public const BUCKETS = [30, 60, 90];

    /**
     * Age outstanding student fees by their due date.
     *
     * @return array{rows: Collection, totals: array<string, float>, buckets: array<int, string>}
     */
    public function studentFees(?string $asOf = null, ?int $formId = null): array
    {
        $asOf = $asOf ? Carbon::parse($asOf) : Carbon::today();

        $fees = StudentFee::query()
            ->with([
                'feeCategory:id,name',
                'enrollment.student:id,student_id,first_name,last_name',
                'enrollment.classSection:id,name,form_id',
                'enrollment.classSection.form:id,name',
            ])
            ->where('balance', '>', 0)
            ->when($formId, fn ($q) => $q->whereHas(
                'enrollment.classSection',
                fn ($s) => $s->where('form_id', $formId)
            ))
            ->get();

        $rows = $fees->map(function (StudentFee $fee) use ($asOf) {
            // A fee with no due date is not yet overdue by any measure, so it
            // sits in the current bucket rather than being guessed at.
            $days = $fee->due_date ? $this->daysOverdue($fee->due_date, $asOf) : 0;

            return (object) [
                'student' => $fee->enrollment?->student,
                'class' => $fee->enrollment?->classSection?->name,
                'category' => $fee->feeCategory?->name,
                'due_date' => $fee->due_date,
                'balance' => (float) $fee->balance,
                'days_overdue' => $days,
                'bucket' => $this->bucketFor($days),
            ];
        })->sortByDesc('days_overdue')->values();

        return [
            'rows' => $rows,
            'totals' => $this->totalsByBucket($rows),
            'buckets' => $this->bucketLabels(),
        ];
    }

    /**
     * Age posted ledger balances on a class-4 sub-group.
     *
     * Each account's outstanding balance is aged by the date of its OLDEST
     * unsettled posting — the standard approximation when the chart of accounts
     * does not carry per-invoice sub-ledgers.
     *
     * @return array{rows: Collection, totals: array<string, float>, buckets: array<int, string>}
     */
    public function ledgerAging(string $normalBalance, ?string $asOf = null): array
    {
        $asOf = $asOf ? Carbon::parse($asOf) : Carbon::today();

        $accounts = ChartOfAccount::postable()
            ->where('class_number', 4)
            ->where('normal_balance', $normalBalance)
            ->orderBy('account_code')
            ->get();

        $rows = $accounts->map(function (ChartOfAccount $account) use ($asOf) {
            $balance = (float) $account->postedBalance(null, $asOf->toDateString());

            if (abs($balance) < 0.01) {
                return null;
            }

            $oldest = JournalEntryLine::where('account_id', $account->id)
                ->whereHas('journalEntry', fn ($q) => $q->where('is_posted', true))
                ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
                ->min('journal_entries.entry_date');

            $days = $oldest ? $this->daysOverdue(Carbon::parse($oldest), $asOf) : 0;

            return (object) [
                'account' => $account,
                'oldest_entry' => $oldest ? Carbon::parse($oldest) : null,
                'balance' => $balance,
                'days_overdue' => $days,
                'bucket' => $this->bucketFor($days),
            ];
        })->filter()->sortByDesc('days_overdue')->values();

        return [
            'rows' => $rows,
            'totals' => $this->totalsByBucket($rows),
            'buckets' => $this->bucketLabels(),
        ];
    }

    private function daysOverdue(CarbonInterface $due, CarbonInterface $asOf): int
    {
        return $due->lt($asOf) ? (int) $due->diffInDays($asOf) : 0;
    }

    /** The bucket label a given age falls into. */
    public function bucketFor(int $days): string
    {
        $lower = 0;

        foreach (self::BUCKETS as $upper) {
            if ($days <= $upper) {
                return $lower === 0 ? '0-'.$upper : ($lower + 1).'-'.$upper;
            }
            $lower = $upper;
        }

        $buckets = self::BUCKETS;

        return end($buckets).'+';
    }

    /** @return array<int, string> */
    public function bucketLabels(): array
    {
        $labels = [];
        $lower = 0;

        foreach (self::BUCKETS as $upper) {
            $labels[] = $lower === 0 ? '0-'.$upper : ($lower + 1).'-'.$upper;
            $lower = $upper;
        }

        $last = self::BUCKETS;
        $labels[] = end($last).'+';

        return $labels;
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array<string, float>
     */
    private function totalsByBucket(Collection $rows): array
    {
        $totals = array_fill_keys($this->bucketLabels(), 0.0);

        foreach ($rows as $row) {
            $totals[$row->bucket] += $row->balance;
        }

        $totals['total'] = array_sum($totals);

        return $totals;
    }
}
