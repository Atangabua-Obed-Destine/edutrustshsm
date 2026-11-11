<?php

namespace App\Services\EdutrustPay;

use App\Models\StudentFee;
use App\Services\AgingReportService;
use Carbon\Carbon;
use EdutrustPay\Contract\Capability;
use EdutrustPay\Contract\Money;
use EdutrustPay\Contract\PayloadBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Assembles one branch's contract payload for one calendar month.
 *
 * The report is GENERATED from the school's own records, never typed by anyone.
 *
 * What this school can currently report is narrow — enrolment and receivables,
 * and no ledger at all — and the payload says so by omission rather than by
 * sending zeroes. See config/edutrustpay.php.
 */
class PeriodReportBuilder
{
    public function __construct(private AgingReportService $ageing)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(int $branchId, string $period, int $sequence = 1, ?string $status = null): array
    {
        $settings = app(SettingsResolver::class)->forBranch($branchId);

        if ($settings === null) {
            throw new \RuntimeException(
                "Branch $branchId has no EdutrustPay credentials. Each branch is a separate institution "
                .'with its own reference and key — set them under Settings → EdutrustPay Reporting.'
            );
        }

        $fees = new FeeSummaryService($branchId, $period);
        $capabilities = (array) config('edutrustpay.capabilities', []);

        $builder = PayloadBuilder::for($settings['institution_ref'], $period)
            ->status($status ?? $this->statusFor($period))
            ->sequence($sequence)
            ->generatedAt(gmdate('Y-m-d\TH:i:s\Z'))
            ->currency('XAF');

        if (in_array(Capability::LEDGER, $capabilities, true)) {
            // Guard rather than silently omit: if somebody adds 'ledger' to the
            // config before the ledger is actually in use, the report must fail
            // here rather than arrive without the block it claims to carry.
            throw new \RuntimeException(
                'This deployment has no posted journal entries, so it cannot report a ledger. Remove '
                .'"ledger" from edutrustpay.capabilities until the accounting module is in use.'
            );
        }

        if (in_array(Capability::ENROLMENT, $capabilities, true)) {
            $this->addEnrolment($builder, $fees);
        }

        if (in_array(Capability::RECEIVABLES, $capabilities, true)) {
            $this->addReceivables($builder, $branchId, $fees->endDate());
        }

        if (in_array(Capability::FEES_CHARGED, $capabilities, true)) {
            $this->addFeesCharged($builder, $branchId, $fees->endDate());
        }

        $people = new PeopleSummaryService($branchId, $period);

        // Students per form, in the session the month falls in. Before the first
        // session there is nothing to count, so the part is left out and the
        // console says it was not sent; a zero would read as an empty school.
        if (in_array(Capability::STUDENTS, $capabilities, true) && ($session = $people->session())) {
            $builder->students($session->name, collect($people->studentsByForm())
                ->map(fn (int $students, string $form) => ['level' => $form, 'students' => $students])
                ->values()
                ->all());
        }

        // Number, name, department and post. Never pay.
        if (in_array(Capability::STAFF, $capabilities, true)) {
            $builder->staff($people->staff());
        }

        // The fee position for the school year, as at month end: expected,
        // collected, owed and overpaid, by form and fee, and how many pupils have
        // paid. Before the first session, nothing is sent.
        if (in_array(Capability::FEES, $capabilities, true) && ($year = (new FeeYearService($branchId, $period))->position())) {
            $builder->fees($year['session'], $year['rows'], $year['students']);
        }

        return $builder->build();
    }

    /**
     * What each form is charged, so the body can compare it with the fees it has
     * set. Amounts and head counts only: no student is named.
     *
     * Both the fee structure and the amounts really billed are reported. A bill
     * can differ from the structure, and that difference is exactly what the body
     * wants to see, while a structure nobody has been billed on yet still says
     * what the school intends to charge. The billed amount is before discounts
     * and waivers, so a bursary does not read as a cheaper fee.
     *
     * Queried by branch directly: the branch scope is inactive in the console.
     * With nothing to report the block is omitted, and the console says it cannot
     * check. A zero would look like a fee of nothing.
     */
    private function addFeesCharged(PayloadBuilder $builder, int $branchId, string $asOf): void
    {
        $session = DB::table('academic_sessions')
            ->where('branch_id', $branchId)
            ->whereDate('start_date', '<=', $asOf)
            ->orderByDesc('start_date')
            ->first();

        if (! $session) {
            return;
        }

        $billed = DB::table('student_fees as sf')
            ->join('student_enrollments as e', 'e.id', '=', 'sf.student_enrollment_id')
            ->join('class_sections as cs', 'cs.id', '=', 'e.class_section_id')
            ->join('forms as f', 'f.id', '=', 'cs.form_id')
            ->join('fee_categories as c', 'c.id', '=', 'sf.fee_category_id')
            ->where('sf.branch_id', $branchId)
            ->where('e.academic_session_id', $session->id)
            ->whereDate('sf.created_at', '<=', $asOf)
            ->selectRaw('f.name as level, c.name as category, sf.original_amount as amount, COUNT(DISTINCT e.student_id) as students')
            ->groupBy('f.name', 'c.name', 'sf.original_amount')
            ->get();

        $set = DB::table('fee_structures as s')
            ->join('forms as f', 'f.id', '=', 's.form_id')
            ->join('fee_categories as c', 'c.id', '=', 's.fee_category_id')
            ->where('s.branch_id', $branchId)
            ->where('s.academic_session_id', $session->id)
            ->whereDate('s.created_at', '<=', $asOf)
            ->selectRaw('f.name as level, c.name as category, s.amount as amount, 0 as students')
            ->get();

        // Billed rows first, so a structure that was billed keeps its head count.
        $rows = [];

        foreach ($billed->concat($set) as $row) {
            $amount = Money::of(number_format((float) $row->amount, 2, '.', ''));

            $rows[$row->level."\0".$row->category."\0".$amount->value()] ??= [
                'level' => $row->level,
                'category' => $row->category,
                'amount' => $amount,
                'students' => (int) $row->students,
            ];
        }

        if ($rows === []) {
            return;
        }

        $builder->feesCharged($session->name, array_values($rows));
    }

    private function addEnrolment(PayloadBuilder $builder, FeeSummaryService $fees): void
    {
        $expected = $fees->expectedFees();
        $collected = $fees->collected();
        $cashShare = $fees->cashShare();

        /*
         * No money has ever been collected, so there is no cash share to state.
         * Sending "0" would claim none of it was cash; the honest move is to
         * withhold the whole enrolment block rather than pad it, since the
         * contract has no way to say "this one figure is unknown".
         */
        if ($cashShare === null) {
            return;
        }

        $builder->enrolment(
            $fees->enrolment(),
            $expected,
            $collected,
            $fees->collectionRate($expected, $collected),
            $cashShare
        );
    }

    /**
     * Outstanding student fees, aged.
     *
     * The local service's buckets are 0-30, 31-60, 61-90 and 90+ — already
     * exactly the contract's four, so nothing is re-bucketed or summed twice.
     * That is a happy accident of both being built around the same OHADA-shaped
     * thinking, and it is asserted below rather than assumed, because a change
     * to AgingReportService::BUCKETS would otherwise silently misfile arrears.
     */
    private function addReceivables(PayloadBuilder $builder, int $branchId, string $asOf): void
    {
        $expectedLabels = ['0-30', '31-60', '61-90', '90+'];
        $labels = $this->ageing->bucketLabels();

        if ($labels !== $expectedLabels) {
            throw new \RuntimeException(sprintf(
                'AgingReportService buckets are now [%s] but the contract expects [%s]. Re-map them in '
                .'this builder before reporting, or arrears will be filed into the wrong ages.',
                implode(', ', $labels),
                implode(', ', $expectedLabels)
            ));
        }

        /*
         * The service is branch-scoped by a global scope that is INACTIVE in the
         * console, so it would otherwise total every campus together. Scoping is
         * applied here explicitly instead.
         */
        $rows = StudentFee::withoutBranchScope()
            ->where('branch_id', $branchId)
            ->where('balance', '>', 0)
            ->get();

        $asOfDate = Carbon::parse($asOf);
        $buckets = array_fill_keys(['0_30', '31_60', '61_90', '90_plus'], '0.00');

        foreach ($rows as $fee) {
            $days = $fee->due_date
                ? max(0, Carbon::parse($fee->due_date)->diffInDays($asOfDate, false))
                : 0;

            $key = match ($this->ageing->bucketFor((int) $days)) {
                '0-30' => '0_30',
                '31-60' => '31_60',
                '61-90' => '61_90',
                default => '90_plus',
            };

            $buckets[$key] = bcadd($buckets[$key], (string) $fee->balance, 2);
        }

        $money = array_map(fn (string $v) => Money::of($v), $buckets);

        // Total is the sum of the buckets, so the two can never disagree.
        $builder->receivables(Money::sum(array_values($money)), $money);
    }

    /**
     * A month is final once it is comfortably past; there is no accounting
     * period here to close, because the accounting module is not in use.
     */
    private function statusFor(string $period): string
    {
        return Carbon::createFromFormat('Y-m-d', $period.'-01')
            ->endOfMonth()
            ->addDays(10)
            ->isPast() ? 'final' : 'provisional';
    }
}
