<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\Payroll;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The cash book.
 *
 * A bursar keeps a livre de caisse: every franc that came in or went out, one
 * row each, in date order, with a running balance and a monthly total under
 * each heading. Nothing in it is typed twice. Every row already exists
 * somewhere in the system — a fee receipt, an income, an expense, a salary
 * paid — and this presents them in the shape the bursar already keeps on
 * paper.
 *
 * Only money that moved is in the book. A receipt settled from a pupil's
 * credit brought no cash, a payment still waiting for the accountant's
 * approval is not cash yet, and a reversed receipt is kept as a record but no
 * longer counts. Salaries enter on the day they were paid, at the net amount
 * that actually left the cash, not at the gross cost the ledger carries.
 *
 * The analysis headings are the system's own categories — fee categories and
 * income categories on the way in, expense categories and salaries on the way
 * out — so there is no mapping to maintain and no row that cannot be placed.
 */
class DaybookService
{
    public const IN = 'in';
    public const OUT = 'out';

    /** Receipts paid from a pupil's credit balance move no cash. */
    private const NON_CASH_METHODS = ['student_credit'];

    /**
     * Every cash movement between two dates, in date order.
     *
     * @return Collection<int, array{
     *   date: string, ref: string, description: string, party: ?string,
     *   method: ?string, direction: string, amount: float,
     *   heading: string, heading_key: string, source: string, source_id: int,
     *   split: array<string, float>
     * }>
     */
    public function rows(string $from, string $to): Collection
    {
        return collect()
            ->merge($this->feeRows($from, $to))
            ->merge($this->incomeRows($from, $to))
            ->merge($this->expenseRows($from, $to))
            ->merge($this->payrollRows($from, $to))
            ->sortBy([['date', 'asc'], ['source', 'asc'], ['source_id', 'asc']])
            ->values();
    }

    /**
     * The book for one month: rows with a running balance, and the totals.
     *
     * The balance brought forward is every movement before the month, from the
     * opening of the chosen year. A bursar's cash book restarts each financial
     * year, so the year's opening is the natural zero.
     *
     * @return array{
     *   rows: Collection, brought_forward: float, total_in: float, total_out: float,
     *   closing: float, headings: array{in: array<string, float>, out: array<string, float>}
     * }
     */
    public function book(Carbon $month, Carbon $yearStart): array
    {
        $from = $month->copy()->startOfMonth();
        $to = $month->copy()->endOfMonth();

        $broughtForward = $from->gt($yearStart)
            ? $this->net($yearStart->toDateString(), $from->copy()->subDay()->toDateString())
            : 0.0;

        $rows = $this->rows($from->toDateString(), $to->toDateString());

        $balance = $broughtForward;
        $in = 0.0;
        $out = 0.0;
        $headings = [self::IN => [], self::OUT => []];

        $rows = $rows->map(function (array $row) use (&$balance, &$in, &$out, &$headings) {
            if ($row['direction'] === self::IN) {
                $in += $row['amount'];
                $balance += $row['amount'];
            } else {
                $out += $row['amount'];
                $balance -= $row['amount'];
            }

            foreach ($row['split'] as $heading => $amount) {
                $headings[$row['direction']][$heading] = ($headings[$row['direction']][$heading] ?? 0) + $amount;
            }

            $row['balance'] = $balance;

            return $row;
        });

        arsort($headings[self::IN]);
        arsort($headings[self::OUT]);

        return [
            'rows' => $rows,
            'brought_forward' => $broughtForward,
            'total_in' => $in,
            'total_out' => $out,
            'closing' => $balance,
            'headings' => $headings,
        ];
    }

    /**
     * The year month by month: in, out, net, and the balance at each month end.
     *
     * One fetch, bucketed, rather than twelve fetches.
     *
     * @return array{
     *   months: array<int, array{label: string, start: string, end: string, in: float, out: float, net: float, balance: float}>,
     *   total_in: float, total_out: float, net: float,
     *   top_in: array<int, array{heading: string, amount: float, share: float}>,
     *   top_out: array<int, array{heading: string, amount: float, share: float}>,
     *   largest: Collection, has_data: bool
     * }
     */
    public function year(Carbon $yearStart, Carbon $yearEnd): array
    {
        $rows = $this->rows($yearStart->toDateString(), $yearEnd->toDateString());

        $months = [];
        foreach (CarbonPeriod::create($yearStart->copy()->startOfMonth(), '1 month', $yearEnd) as $start) {
            $months[] = [
                'label' => $start->translatedFormat('M Y'),
                'month' => $start->format('Y-m'),
                'start' => $start->toDateString(),
                'end' => $start->copy()->endOfMonth()->toDateString(),
                'in' => 0.0, 'out' => 0.0, 'net' => 0.0, 'balance' => 0.0,
            ];
        }

        $byHeading = [self::IN => [], self::OUT => []];
        $totalIn = 0.0;
        $totalOut = 0.0;

        foreach ($rows as $row) {
            foreach ($months as $i => $m) {
                if ($row['date'] >= $m['start'] && $row['date'] <= $m['end']) {
                    $months[$i][$row['direction']] += $row['amount'];
                    break;
                }
            }

            $row['direction'] === self::IN ? $totalIn += $row['amount'] : $totalOut += $row['amount'];

            foreach ($row['split'] as $heading => $amount) {
                $byHeading[$row['direction']][$heading] = ($byHeading[$row['direction']][$heading] ?? 0) + $amount;
            }
        }

        $balance = 0.0;
        foreach ($months as $i => $m) {
            $months[$i]['net'] = $m['in'] - $m['out'];
            $balance += $months[$i]['net'];
            $months[$i]['balance'] = $balance;
        }

        return [
            'months' => $months,
            'total_in' => $totalIn,
            'total_out' => $totalOut,
            'net' => $totalIn - $totalOut,
            'top_in' => $this->ranked($byHeading[self::IN], $totalIn),
            'top_out' => $this->ranked($byHeading[self::OUT], $totalOut),
            'largest' => $rows->sortByDesc('amount')->take(10)->values(),
            'has_data' => $rows->isNotEmpty(),
        ];
    }

    /**
     * The financial year the book is kept in.
     *
     * A school's money year is its academic year — fees arrive in September,
     * not January — so the current session's dates are the default, and a
     * school with no session yet gets the calendar year.
     *
     * @return array{start: Carbon, end: Carbon, label: string}
     */
    public function yearFor(?Carbon $date = null): array
    {
        $date ??= now();

        $session = AcademicSession::query()
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->orderByDesc('start_date')
            ->first() ?? AcademicSession::current();

        if ($session && $session->start_date && $session->end_date) {
            return [
                'start' => Carbon::parse($session->start_date)->startOfDay(),
                'end' => Carbon::parse($session->end_date)->endOfDay(),
                'label' => $session->name,
            ];
        }

        return [
            'start' => $date->copy()->startOfYear(),
            'end' => $date->copy()->endOfYear(),
            'label' => $date->format('Y'),
        ];
    }

    /** Net cash movement between two dates. */
    private function net(string $from, string $to): float
    {
        return $this->rows($from, $to)->sum(fn ($r) => $r['direction'] === self::IN ? $r['amount'] : -$r['amount']);
    }

    /**
     * @param  array<string, float>  $totals
     * @return array<int, array{heading: string, amount: float, share: float}>
     */
    private function ranked(array $totals, float $sideTotal, int $limit = 8): array
    {
        arsort($totals);

        return collect($totals)->take($limit)->map(fn ($amount, $heading) => [
            'heading' => $heading,
            'amount' => (float) $amount,
            'share' => $sideTotal > 0 ? ($amount / $sideTotal) * 100 : 0.0,
        ])->values()->all();
    }

    /* ------------------------------------------------------------ sources */

    /**
     * Fee receipts. One row per receipt — that is the cash movement — with the
     * amount split across the fee categories it settled, so the monthly
     * headings can say how much was tuition and how much was PTA levy.
     */
    private function feeRows(string $from, string $to): array
    {
        $payments = DB::table('payments as p')
            ->join('student_enrollments as e', 'e.id', '=', 'p.student_enrollment_id')
            ->join('students as s', 's.id', '=', 'e.student_id')
            ->where('p.verification_status', 'verified')
            ->whereNotIn('p.payment_method', self::NON_CASH_METHODS)
            ->whereDate('p.payment_date', '>=', $from)->whereDate('p.payment_date', '<=', $to)
            ->select('p.id', 'p.receipt_number', 'p.payment_date', 'p.amount', 'p.payment_method', 'p.payer_name',
                's.first_name', 's.last_name', 's.student_id as matricule')
            ->get();

        if ($payments->isEmpty()) {
            return [];
        }

        $splits = DB::table('payment_allocations as a')
            ->join('student_fees as f', 'f.id', '=', 'a.student_fee_id')
            ->join('fee_categories as c', 'c.id', '=', 'f.fee_category_id')
            ->whereIn('a.payment_id', $payments->pluck('id'))
            ->selectRaw('a.payment_id, c.name as heading, SUM(a.amount) as amount')
            ->groupBy('a.payment_id', 'c.name')
            ->get()
            ->groupBy('payment_id');

        return $payments->map(function ($p) use ($splits) {
            $split = $splits->get($p->id, collect())->pluck('amount', 'heading')->map(fn ($v) => (float) $v)->all();

            // A receipt not yet allocated to any fee still arrived as cash.
            $placed = array_sum($split);
            if ($placed + 0.009 < (float) $p->amount) {
                $split[__('School fees')] = ($split[__('School fees')] ?? 0) + ((float) $p->amount - $placed);
            }

            return [
                'date' => substr((string) $p->payment_date, 0, 10),
                'ref' => $p->receipt_number,
                'description' => __('Fees — :student', ['student' => trim($p->first_name.' '.$p->last_name)]),
                'party' => $p->payer_name ?: $p->matricule,
                'method' => $p->payment_method,
                'direction' => self::IN,
                'amount' => (float) $p->amount,
                'heading' => count($split) === 1 ? array_key_first($split) : __('School fees'),
                'source' => 'payment',
                'source_id' => (int) $p->id,
                'split' => $split,
            ];
        })->all();
    }

    private function incomeRows(string $from, string $to): array
    {
        return DB::table('incomes as i')
            ->join('income_categories as c', 'c.id', '=', 'i.category_id')
            ->where('i.status', true)
            ->whereDate('i.date', '>=', $from)->whereDate('i.date', '<=', $to)
            ->select('i.id', 'i.title', 'i.date', 'i.amount', 'i.reference', 'i.payment_method', 'c.title as heading')
            ->get()
            ->map(fn ($i) => [
                'date' => substr((string) $i->date, 0, 10),
                'ref' => $i->reference ?: __('INC-:id', ['id' => $i->id]),
                'description' => $i->title,
                'party' => null,
                'method' => $i->payment_method,
                'direction' => self::IN,
                'amount' => (float) $i->amount,
                'heading' => $i->heading,
                'source' => 'income',
                'source_id' => (int) $i->id,
                'split' => [$i->heading => (float) $i->amount],
            ])->all();
    }

    private function expenseRows(string $from, string $to): array
    {
        return DB::table('expenses as e')
            ->join('expense_categories as c', 'c.id', '=', 'e.category_id')
            ->where('e.status', true)
            ->where('e.approval_status', '!=', 'rejected')
            ->whereDate('e.date', '>=', $from)->whereDate('e.date', '<=', $to)
            ->select('e.id', 'e.title', 'e.date', 'e.amount', 'e.reference', 'e.payment_method', 'c.title as heading')
            ->get()
            ->map(fn ($e) => [
                'date' => substr((string) $e->date, 0, 10),
                'ref' => $e->reference ?: __('EXP-:id', ['id' => $e->id]),
                'description' => $e->title,
                'party' => null,
                'method' => $e->payment_method,
                'direction' => self::OUT,
                'amount' => (float) $e->amount,
                'heading' => $e->heading,
                'source' => 'expense',
                'source_id' => (int) $e->id,
                'split' => [$e->heading => (float) $e->amount],
            ])->all();
    }

    /** Salaries, on the day they were paid, at what left the cash. */
    private function payrollRows(string $from, string $to): array
    {
        return DB::table('payrolls as p')
            ->join('users as u', 'u.id', '=', 'p.user_id')
            ->where('p.status', Payroll::STATUS_PAID)
            ->whereNotNull('p.pay_date')
            ->whereDate('p.pay_date', '>=', $from)->whereDate('p.pay_date', '<=', $to)
            ->select('p.id', 'p.pay_date', 'p.net_salary', 'p.salary_month', 'p.payment_method', 'u.first_name', 'u.last_name')
            ->get()
            ->map(fn ($p) => [
                'date' => substr((string) $p->pay_date, 0, 10),
                'ref' => __('PAY-:id', ['id' => $p->id]),
                'description' => __('Salary :month — :staff', ['month' => $p->salary_month, 'staff' => trim($p->first_name.' '.$p->last_name)]),
                'party' => null,
                'method' => $p->payment_method,
                'direction' => self::OUT,
                'amount' => (float) $p->net_salary,
                'heading' => __('Salaries'),
                'source' => 'payroll',
                'source_id' => (int) $p->id,
                'split' => [__('Salaries') => (float) $p->net_salary],
            ])->all();
    }
}
