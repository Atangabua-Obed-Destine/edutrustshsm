<?php

namespace App\Services\EdutrustPay;

use App\Models\Payment;
use App\Models\StudentEnrollment;
use App\Models\StudentFee;
use Carbon\Carbon;
use EdutrustPay\Contract\Money;

/**
 * Summarises one calendar month of enrolment and fee collection for a branch.
 *
 * This is what this school can honestly report today. Its general ledger is
 * empty, so there is nothing here about income by OHADA group or cash position —
 * see config/edutrustpay.php.
 *
 * WHY THE FIGURES HERE MATTER MORE THAN THEY LOOK
 *
 * expected_fees against collected is the arithmetic that reaches the two kinds
 * of loss a ledger cannot see: cash received and never entered, and students
 * attending who were never billed. And cash_share — the proportion taken as
 * physical cash — is the exposure measure for interception. This school records
 * payment_method on every payment, so that share is a real observation rather
 * than an estimate.
 *
 * Everything is branch-scoped explicitly. These run from the console where
 * BranchContext is inactive and the global scope is a no-op, so relying on it
 * would silently report every campus as one school.
 */
class FeeSummaryService
{
    private string $start;

    private string $end;

    public function __construct(private int $branchId, string $period)
    {
        $month = Carbon::createFromFormat('Y-m-d', $period.'-01')->startOfMonth();

        $this->start = $month->toDateString();
        $this->end = $month->copy()->endOfMonth()->toDateString();
    }

    /**
     * Students on the roll at the end of the month.
     *
     * Counts enrolments that were active, not students who ever existed: a
     * withdrawn student is not somebody the school should be billing, and
     * including them would inflate expected fees and manufacture a collection
     * gap that is not there.
     */
    public function enrolment(): int
    {
        return (int) StudentEnrollment::withoutBranchScope()
            ->where('branch_id', $this->branchId)
            ->whereIn('status', ['active', 'promoted', 'repeated'])
            ->whereDate('enrollment_date', '<=', $this->end)
            ->count();
    }

    /**
     * What the school has billed, as at the end of the month.
     *
     * net_amount, so discounts, waivers and fines are all reflected — the figure
     * is what is actually owed, not the list price.
     */
    public function expectedFees(): Money
    {
        return Money::fromNumeric((string) (StudentFee::withoutBranchScope()
            ->where('branch_id', $this->branchId)
            ->whereDate('created_at', '<=', $this->end)
            ->sum('net_amount') ?? 0));
    }

    /**
     * Cash actually received against fees, cumulatively to the end of the month.
     *
     * Cumulative rather than in-month so that it is comparable with
     * expected_fees above; a single month's receipts against a whole year's
     * billing would look like a catastrophic collection failure every month.
     */
    public function collected(): Money
    {
        return Money::fromNumeric((string) ($this->paymentsQuery()
            ->whereDate('payment_date', '<=', $this->end)
            ->sum('amount') ?? 0));
    }

    /**
     * The proportion of collected fees taken as physical cash.
     *
     * Measured over the SAME WINDOW as collected() — cumulative to the end of
     * the month — and that consistency was learned the hard way.
     *
     * Measuring it in-month is the more useful question, because a cash share
     * that climbs month on month is the signal worth seeing. But a month with no
     * receipts then has no cash share at all, and the contract's enrolment block
     * has a single required slot for it: the only thing that could be sent is
     * "0", which reads as "none of it was cash" rather than "there was nothing
     * to take". On this school's real data that produced a confident 0.0000 for
     * every month — a false figure of exactly the kind this platform exists to
     * prevent, in the platform's own client.
     *
     * So it matches collected(): both cumulative, both defined whenever any
     * money has ever come in, and the two are directly comparable.
     *
     * CONTRACT LIMITATION worth fixing upstream: cash_share cannot currently
     * express "no receipts this period", and it cannot carry both the in-month
     * and the running share. Either would need a contract change.
     *
     * Returns null only when nothing has ever been collected.
     */
    public function cashShare(): ?string
    {
        $total = (float) ($this->paymentsQuery()
            ->whereDate('payment_date', '<=', $this->end)
            ->sum('amount') ?? 0);

        if ($total <= 0) {
            return null;
        }

        $cash = (float) ($this->paymentsQuery()
            ->whereDate('payment_date', '<=', $this->end)
            ->where('payment_method', 'cash')
            ->sum('amount') ?? 0);

        return number_format(min(1, $cash / $total), 4, '.', '');
    }

    public function collectionRate(Money $expected, Money $collected): string
    {
        $expectedFloat = (float) $expected->value();

        if ($expectedFloat <= 0) {
            return '0';
        }

        return number_format(min(1, (float) $collected->value() / $expectedFloat), 4, '.', '');
    }

    /**
     * Only verified payments count.
     *
     * A payment still awaiting verification is a claim that money arrived, not
     * evidence of it — and counting claims would defeat the point of comparing
     * collections against what was billed.
     */
    private function paymentsQuery()
    {
        return Payment::withoutBranchScope()
            ->where('branch_id', $this->branchId)
            ->where('verification_status', 'verified');
    }

    public function endDate(): string
    {
        return $this->end;
    }
}
