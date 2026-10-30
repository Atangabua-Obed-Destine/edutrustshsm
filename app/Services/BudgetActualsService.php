<?php

namespace App\Services;

use App\Models\BudgetLine;
use App\Models\Payroll;
use Illuminate\Support\Facades\DB;

/**
 * Works out what was actually received and spent against each budget line.
 *
 * This is the only place that decides where real money lands on the sheet,
 * so the figure the sheet shows, the figure the daybook analyses and the
 * figure the monthly summary carries are the same figure by construction.
 *
 * Fees, incomes and expenses are read from their own tables, so the sheet
 * works whether or not the ledger has been mapped. Salaries are read from
 * paid payroll runs at their full cost to the school.
 *
 * Two rules matter more than the rest:
 *   - a transaction is counted once, against exactly one line;
 *   - money that cannot be placed is reported as unallocated, never dropped.
 */
class BudgetActualsService
{
    /** Receipts paid from a pupil's credit balance move no cash. */
    private const NON_CASH_METHODS = ['student_credit'];

    /**
     * Actual amounts keyed by budget line id, for a date range.
     *
     * @return array{lines: array<int, float>, unallocated: array<string, float>}
     */
    public function forPeriod(?string $from = null, ?string $to = null): array
    {
        $lines = [];
        $unallocated = [];

        $this->addExpenses($lines, $unallocated, $from, $to);
        $this->addIncomes($lines, $unallocated, $from, $to);
        $this->addFees($lines, $unallocated, $from, $to);
        $this->addPayroll($lines, $unallocated, $from, $to);

        return ['lines' => $lines, 'unallocated' => $unallocated];
    }

    /** Expenditure, routed by the row's own line or its category's mapping. */
    private function addExpenses(array &$lines, array &$unallocated, ?string $from, ?string $to): void
    {
        $rows = DB::table('expenses as e')
            ->leftJoin('default_account_mappings as m', function ($join) {
                $join->on('m.category_id', '=', 'e.category_id')->where('m.mapping_type', '=', 'expense');
            })
            ->selectRaw('COALESCE(e.budget_line_id, m.budget_line_id) as line_id, SUM(e.amount) as total')
            ->where('e.status', true)
            ->where('e.approval_status', '!=', 'rejected')
            ->when($from, fn ($q) => $q->whereDate('e.date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('e.date', '<=', $to))
            ->groupBy('line_id')
            ->get();

        $this->place($rows, $lines, $unallocated, 'expenses');
    }

    /** Recorded income other than pupils' fees. */
    private function addIncomes(array &$lines, array &$unallocated, ?string $from, ?string $to): void
    {
        $rows = DB::table('incomes as i')
            ->leftJoin('default_account_mappings as m', function ($join) {
                $join->on('m.category_id', '=', 'i.category_id')->where('m.mapping_type', '=', 'income');
            })
            ->selectRaw('COALESCE(i.budget_line_id, m.budget_line_id) as line_id, SUM(i.amount) as total')
            ->where('i.status', true)
            ->when($from, fn ($q) => $q->whereDate('i.date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('i.date', '<=', $to))
            ->groupBy('line_id')
            ->get();

        $this->place($rows, $lines, $unallocated, 'incomes');
    }

    /**
     * Pupils' fees, the cash actually received, in the month it arrived.
     *
     * A receipt is split by what it settled: each allocation goes to its fee
     * category's line. Tuition is split by school level — the line tagged
     * with the pupil's level wins over the category's own mapping, so nursery
     * and primary tuition land on their own rows. The part of a receipt not
     * allocated to any fee has no category and is reported as unallocated.
     */
    private function addFees(array &$lines, array &$unallocated, ?string $from, ?string $to): void
    {
        $levelLines = BudgetLine::query()->active()->postable()->whereNotNull('school_level')->pluck('id', 'school_level');

        $payments = DB::table('payments as p')
            ->where('p.verification_status', 'verified')
            ->whereNotIn('p.payment_method', self::NON_CASH_METHODS)
            ->when($from, fn ($q) => $q->whereDate('p.payment_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('p.payment_date', '<=', $to));

        $allocated = DB::table('payment_allocations as pa')
            ->joinSub($payments->select('p.id'), 'vp', 'vp.id', '=', 'pa.payment_id')
            ->join('student_fees as sf', 'sf.id', '=', 'pa.student_fee_id')
            ->join('fee_categories as fc', 'fc.id', '=', 'sf.fee_category_id')
            ->leftJoin('default_account_mappings as m', function ($join) {
                $join->on('m.category_id', '=', 'sf.fee_category_id')->where('m.mapping_type', '=', 'fee_payment');
            })
            ->leftJoin('student_enrollments as se', 'se.id', '=', 'sf.student_enrollment_id')
            ->leftJoin('class_sections as cs', 'cs.id', '=', 'se.class_section_id')
            ->leftJoin('forms as f', 'f.id', '=', 'cs.form_id')
            ->selectRaw('m.budget_line_id as mapped_line, fc.is_tuition, f.level, SUM(pa.amount) as total')
            ->groupBy('m.budget_line_id', 'fc.is_tuition', 'f.level')
            ->get();

        foreach ($allocated as $row) {
            $amount = (float) $row->total;
            if ($amount == 0.0) {
                continue;
            }

            $lineId = null;
            if ($row->is_tuition && $row->level && isset($levelLines[$row->level])) {
                $lineId = (int) $levelLines[$row->level];
            } elseif ($row->mapped_line) {
                $lineId = (int) $row->mapped_line;
            }

            if ($lineId === null) {
                $unallocated['fees'] = ($unallocated['fees'] ?? 0) + $amount;

                continue;
            }
            $lines[$lineId] = ($lines[$lineId] ?? 0) + $amount;
        }

        // Cash received beyond what was allocated to a fee — an advance, or a
        // receipt nobody has allocated yet. Still cash in, so still reported.
        $received = (clone $payments)->sum('p.amount');
        $placed = $allocated->sum('total');
        $remainder = round((float) $received - (float) $placed, 2);

        if ($remainder > 0) {
            $unallocated['fees'] = ($unallocated['fees'] ?? 0) + $remainder;
        }
    }

    /**
     * Salaries, at their full cost to the school, on the day they were paid.
     * A run not yet paid is not a cost yet and does not appear.
     */
    private function addPayroll(array &$lines, array &$unallocated, ?string $from, ?string $to): void
    {
        $total = (float) DB::table('payrolls as p')
            ->where('p.status', Payroll::STATUS_PAID)
            ->whereNotNull('p.pay_date')
            ->when($from, fn ($q) => $q->whereDate('p.pay_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('p.pay_date', '<=', $to))
            ->selectRaw('SUM(CASE WHEN p.total_cost > 0 THEN p.total_cost ELSE p.gross_salary END) as total')
            ->value('total');

        if ($total == 0.0) {
            return;
        }

        $line = BudgetLine::query()->active()->postable()->where('is_payroll', true)->value('id');

        if ($line === null) {
            $unallocated['payroll'] = ($unallocated['payroll'] ?? 0) + $total;

            return;
        }
        $lines[(int) $line] = ($lines[(int) $line] ?? 0) + $total;
    }

    /** @param  \Illuminate\Support\Collection<int, object>  $rows */
    private function place($rows, array &$lines, array &$unallocated, string $source): void
    {
        foreach ($rows as $row) {
            $amount = (float) $row->total;
            if ($row->line_id === null) {
                $unallocated[$source] = ($unallocated[$source] ?? 0) + $amount;

                continue;
            }
            $lines[(int) $row->line_id] = ($lines[(int) $row->line_id] ?? 0) + $amount;
        }
    }

    /**
     * The line a transaction would land on today, for the forms: so the
     * expense screen can say "401 Travelling, from the category" before the
     * bursar overrides it.
     */
    public function lineForCategory(string $mappingType, ?int $categoryId): ?BudgetLine
    {
        if (! $categoryId) {
            return null;
        }

        $lineId = DB::table('default_account_mappings')
            ->where('mapping_type', $mappingType)
            ->where('category_id', $categoryId)
            ->value('budget_line_id');

        return $lineId ? BudgetLine::find($lineId) : null;
    }
}
