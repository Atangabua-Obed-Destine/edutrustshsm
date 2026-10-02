<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\JournalEntryLine;
use Illuminate\Support\Collection;

/**
 * The OHADA cash flow statement (tableau des flux de trésorerie), indirect
 * method, derived entirely from posted journal lines.
 *
 * Every posted entry balances, so the change in the cash accounts over a period
 * is exactly the sum of (credit − debit) across every OTHER account. The
 * statement therefore sorts every non-cash account into exactly one line —
 * nothing is picked out by a hand-maintained list of code prefixes and nothing
 * falls through — which makes it reconcile to the cash accounts by
 * construction. The reference system selects accounts by prefix and then checks
 * whether the result happened to match; here a mismatch can only mean an
 * unbalanced posted entry, and the statement says so.
 *
 * Sorting follows the SYSCOHADA chart:
 *
 *   cash         class 5, except 59 (provisions on treasury)
 *   net result   classes 6, 7, 8, plus 12 and 13 — so a period that includes
 *                the year-end closing entry still shows its result, which that
 *                entry moves out of 6/7 and into 13
 *   operating    28, 29, 19, 59 (depreciation and provisions, added back);
 *                class 3 (inventories); class 4 (third parties)
 *   investing    class 2, except 28 and 29
 *   financing    10, 11, 14, 15 (equity, grants); 16, 17, 18 (borrowings)
 */
class CashFlowStatementService
{
    /**
     * @return array{
     *     from: string, to: string,
     *     opening_cash: float, closing_cash: float,
     *     sections: array<string, array{title: string, items: array<int, array{key: string, label: string, amount: float}>, total: float}>,
     *     net_change: float, difference: float, balanced: bool
     * }
     */
    public function statement(string $from, string $to): array
    {
        $lines = [
            'operating' => [],
            'investing' => [],
            'financing' => [],
        ];

        $add = function (string $section, string $key, string $label, float $amount) use (&$lines): void {
            $lines[$section][$key] ??= ['key' => $key, 'label' => $label, 'amount' => 0.0];
            $lines[$section][$key]['amount'] += $amount;
        };

        // The result comes first on the statement even when it is zero.
        $add('operating', 'net_result', __('Net result for the period'), 0.0);

        foreach ($this->movements($from, $to) as $m) {
            $code = $m->code;
            $net = $m->credit - $m->debit;   // this account's contribution to cash

            match (true) {
                $this->isCash($code) => null,

                str_starts_with($code, '6') || str_starts_with($code, '7') || str_starts_with($code, '8')
                    || str_starts_with($code, '12') || str_starts_with($code, '13')
                    => $add('operating', 'net_result', __('Net result for the period'), $net),

                str_starts_with($code, '28') || str_starts_with($code, '29')
                    || str_starts_with($code, '19') || str_starts_with($code, '59')
                    => $add('operating', 'depreciation', __('Depreciation and provisions (non-cash)'), $net),

                str_starts_with($code, '3') => $add('operating', 'inventories', __('Change in inventories'), $net),
                str_starts_with($code, '40') => $add('operating', 'suppliers', __('Change in amounts owed to suppliers'), $net),
                str_starts_with($code, '41') => $add('operating', 'customers', __('Change in amounts owed by students and customers'), $net),
                str_starts_with($code, '42') || str_starts_with($code, '43')
                    => $add('operating', 'staff', __('Change in amounts owed to staff and social security'), $net),
                str_starts_with($code, '44') => $add('operating', 'state', __('Change in taxes owed to the State'), $net),
                str_starts_with($code, '4') => $add('operating', 'other_third_parties', __('Change in other third-party balances'), $net),

                // Investing is shown gross: buying and selling assets in the same
                // period should not net off into an unexplained small number.
                str_starts_with($code, '2') => (function () use ($add, $m) {
                    $add('investing', 'acquisitions', __('Acquisition of fixed assets'), -$m->debit);
                    $add('investing', 'disposals', __('Disposal of fixed assets'), $m->credit);
                })(),

                str_starts_with($code, '16') || str_starts_with($code, '17') || str_starts_with($code, '18')
                    => $add('financing', 'borrowings', __('Borrowings received less repaid'), $net),
                str_starts_with($code, '1') => $add('financing', 'equity', __('Capital, reserves and grants'), $net),

                // Anything outside the chart's classes still moved cash, so it is
                // shown rather than dropped — dropping it would break the check.
                default => $add('operating', 'other', __('Other movements'), $net),
            };
        }

        $titles = [
            'operating' => __('Cash flows from operating activities'),
            'investing' => __('Cash flows from investing activities'),
            'financing' => __('Cash flows from financing activities'),
        ];

        $sections = [];

        foreach ($lines as $section => $items) {
            $items = array_values(array_filter(
                array_map(fn ($i) => ['key' => $i['key'], 'label' => $i['label'], 'amount' => round($i['amount'], 2)], $items),
                fn ($i) => $i['key'] === 'net_result' || abs($i['amount']) >= 0.01,
            ));

            $sections[$section] = [
                'title' => $titles[$section],
                'items' => $items,
                'total' => round(array_sum(array_column($items, 'amount')), 2),
            ];
        }

        $opening = $this->cashAt(date('Y-m-d', strtotime($from.' -1 day')));
        $closing = $this->cashAt($to);
        $netChange = round(array_sum(array_column($sections, 'total')), 2);
        $difference = round($closing - ($opening + $netChange), 2);

        return [
            'from' => $from,
            'to' => $to,
            'opening_cash' => $opening,
            'closing_cash' => $closing,
            'sections' => $sections,
            'net_change' => $netChange,
            'difference' => $difference,
            'balanced' => abs($difference) < 0.01,
        ];
    }

    /**
     * Two periods side by side, with the variance on every line.
     *
     * @return array{current: array<string, mixed>, previous: array<string, mixed>, variance: array<string, array<string, float>>}
     */
    public function comparative(string $from, string $to, string $previousFrom, string $previousTo): array
    {
        $current = $this->statement($from, $to);
        $previous = $this->statement($previousFrom, $previousTo);

        $variance = [];

        foreach (array_keys($current['sections']) as $section) {
            $prior = collect($previous['sections'][$section]['items'])->keyBy('key');

            // Lines present only in the earlier period still belong on the page.
            foreach ($prior as $key => $item) {
                if (! collect($current['sections'][$section]['items'])->contains('key', $key)) {
                    $current['sections'][$section]['items'][] = ['key' => $key, 'label' => $item['label'], 'amount' => 0.0];
                }
            }

            foreach ($current['sections'][$section]['items'] as $item) {
                $variance[$section][$item['key']] = round($item['amount'] - (float) ($prior[$item['key']]['amount'] ?? 0), 2);
            }

            $variance[$section]['_total'] = round(
                $current['sections'][$section]['total'] - $previous['sections'][$section]['total'],
                2
            );
        }

        return ['current' => $current, 'previous' => $previous, 'variance' => $variance];
    }

    /** Cash is class 5, less provisions on treasury (59). */
    public function isCash(string $code): bool
    {
        return str_starts_with($code, '5') && ! str_starts_with($code, '59');
    }

    /**
     * Posted debits and credits per account over the period.
     *
     * @return Collection<int, object{code: string, debit: float, credit: float}>
     */
    private function movements(string $from, string $to): Collection
    {
        $rows = JournalEntryLine::query()
            ->whereHas('journalEntry', fn ($q) => $q->where('is_posted', true)
                ->whereDate('entry_date', '>=', $from)
                ->whereDate('entry_date', '<=', $to))
            ->selectRaw('account_id, SUM(debit) as debit_total, SUM(credit) as credit_total')
            ->groupBy('account_id')
            ->get();

        // Deleted accounts still carry history, and dropping their lines would
        // unbalance the statement.
        $codes = ChartOfAccount::withTrashed()
            ->whereIn('id', $rows->pluck('account_id'))
            ->pluck('account_code', 'id');

        return $rows->map(fn ($r) => (object) [
            'code' => (string) ($codes[$r->account_id] ?? ''),
            'debit' => round((float) $r->debit_total, 2),
            'credit' => round((float) $r->credit_total, 2),
        ]);
    }

    /** Posted balance of the cash accounts at the end of a day. */
    private function cashAt(string $date): float
    {
        $cash = ChartOfAccount::withTrashed()
            ->where('account_code', 'like', '5%')
            ->where('account_code', 'not like', '59%')
            ->pluck('id');

        if ($cash->isEmpty()) {
            return 0.0;
        }

        $row = JournalEntryLine::query()
            ->whereIn('account_id', $cash)
            ->whereHas('journalEntry', fn ($q) => $q->where('is_posted', true)->whereDate('entry_date', '<=', $date))
            ->selectRaw('COALESCE(SUM(debit), 0) as debit_total, COALESCE(SUM(credit), 0) as credit_total')
            ->first();

        return round((float) $row->debit_total - (float) $row->credit_total, 2);
    }
}
