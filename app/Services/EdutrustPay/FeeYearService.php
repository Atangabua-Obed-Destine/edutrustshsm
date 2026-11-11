<?php

namespace App\Services\EdutrustPay;

use Carbon\Carbon;
use EdutrustPay\Contract\Money;
use Illuminate\Support\Facades\DB;

/**
 * One branch's fee position for the school year a month falls in, as at the
 * end of that month: what is expected, collected, still owed and overpaid,
 * form by form and fee by fee, and how many pupils have paid. No pupil is named.
 *
 * WHICH FEES
 *
 * Fees billed on an enrolment in the session the month falls in (the latest
 * one started by month end, the same choice the students and fees charged
 * parts make), billed by month end. Arrears from earlier years stay with the
 * year they were billed in, as every screen here shows them.
 *
 * EXPECTED, COLLECTED, OWED
 *
 * Expected is the fee's net amount: original, less discount and waiver, plus
 * any fine (StudentFee::recalculate()). A fee waived in full expects nothing.
 * Collected is what VERIFIED payments dated by month end allocated to the fee:
 * this system records which payment paid which fee and when, so the month-end
 * position is exact, and a payment still awaiting verification is a claim,
 * not money. Owed and overpaid are worked out fee by fee, so one pupil paying
 * too much never hides another's debt.
 *
 * Amounts are worked in cents (integers) and only turned into Money at the
 * end, so no sum drifts. Queried by branch directly: the branch scope is
 * inactive when this runs from the scheduler.
 */
class FeeYearService
{
    private string $end;

    public function __construct(private int $branchId, private string $period)
    {
        $this->end = Carbon::createFromFormat('Y-m-d', $period.'-01')->endOfMonth()->toDateString();
    }

    /**
     * @return array{
     *     session: string,
     *     rows: list<array{level: string, category: string, expected: Money, collected: Money, owed: Money, overpaid: Money}>,
     *     students: array{paid: int, part_paid: int, not_paid: int, not_charged: int}
     * }|null  null before any session has started
     */
    public function position(): ?array
    {
        $people = new PeopleSummaryService($this->branchId, $this->period);
        $session = $people->session();

        if (! $session) {
            return null;
        }

        $paidByMonthEnd = DB::table('payment_allocations as pa')
            ->join('payments as p', 'p.id', '=', 'pa.payment_id')
            ->where('p.verification_status', 'verified')
            ->whereDate('p.payment_date', '<=', $this->end)
            ->groupBy('pa.student_fee_id')
            ->selectRaw('pa.student_fee_id, SUM(pa.amount) as paid');

        $fees = DB::table('student_fees as sf')
            ->join('student_enrollments as e', 'e.id', '=', 'sf.student_enrollment_id')
            ->leftJoin('class_sections as cs', 'cs.id', '=', 'e.class_section_id')
            ->leftJoin('forms as f', 'f.id', '=', 'cs.form_id')
            ->leftJoin('fee_categories as c', 'c.id', '=', 'sf.fee_category_id')
            ->leftJoinSub($paidByMonthEnd, 'paid', 'paid.student_fee_id', '=', 'sf.id')
            ->where('sf.branch_id', $this->branchId)
            ->where('e.academic_session_id', $session->id)
            ->whereDate('sf.created_at', '<=', $this->end)
            ->orderBy('sf.id')
            ->get(['sf.id', 'e.student_id', 'f.name as level', 'c.name as category', 'sf.net_amount as expected', 'paid.paid as collected']);

        $rows = [];
        $students = [];

        foreach ($fees as $fee) {
            $expected = max(0, self::cents($fee->expected));
            $collected = max(0, self::cents($fee->collected ?? 0));
            $owed = max(0, $expected - $collected);
            $overpaid = max(0, $collected - $expected);

            $level = trim((string) $fee->level) !== '' ? (string) $fee->level : 'No class';
            $category = trim((string) $fee->category) !== '' ? (string) $fee->category : 'Uncategorised';
            $key = $level."\x1F".$category;

            $rows[$key] ??= ['level' => $level, 'category' => $category, 'expected' => 0, 'collected' => 0, 'owed' => 0, 'overpaid' => 0];
            $rows[$key]['expected'] += $expected;
            $rows[$key]['collected'] += $collected;
            $rows[$key]['owed'] += $owed;
            $rows[$key]['overpaid'] += $overpaid;

            $students[$fee->student_id] ??= ['collected' => 0, 'owed' => 0];
            $students[$fee->student_id]['collected'] += $collected;
            $students[$fee->student_id]['owed'] += $owed;
        }

        $counts = ['paid' => 0, 'part_paid' => 0, 'not_paid' => 0, 'not_charged' => 0];

        foreach ($students as $money) {
            $counts[$money['owed'] === 0 ? 'paid' : ($money['collected'] > 0 ? 'part_paid' : 'not_paid')]++;
        }

        // On the roll for the session, but no fee at all.
        $counts['not_charged'] = count(array_diff_key($people->studentForms(), $students));

        return [
            'session' => (string) $session->name,
            'rows' => array_values(array_map(fn (array $row) => [
                'level' => $row['level'],
                'category' => $row['category'],
                'expected' => self::money($row['expected']),
                'collected' => self::money($row['collected']),
                'owed' => self::money($row['owed']),
                'overpaid' => self::money($row['overpaid']),
            ], $rows)),
            'students' => $counts,
        ];
    }

    private static function cents($amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    private static function money(int $cents): Money
    {
        return Money::of(sprintf('%s%d.%02d', $cents < 0 ? '-' : '', intdiv(abs($cents), 100), abs($cents) % 100));
    }
}
