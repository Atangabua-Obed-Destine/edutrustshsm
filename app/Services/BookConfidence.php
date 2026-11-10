<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\Payroll;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Can these figures be taken into a meeting?
 *
 * Every figure on the analysis is arithmetically right, and can still mislead,
 * because it says nothing about the money that has not reached the book yet.
 * These are the few facts that decide whether a number is safe to present, so
 * the bursar meets them before the meeting rather than a board member during
 * it. Follows the reference system's ledger confidence checks. Read-only.
 */
class BookConfidence
{
    /**
     * @param  array{amount: float, count: int}  $unanalysed  from DaybookService::analysis()
     * @return array<int, array{key: string, ok: bool, label: string, detail: string, route: ?string}>
     */
    public function checks(Carbon $from, Carbon $to, ?Budget $sheet, array $unanalysed): array
    {
        $checks = [
            $this->sheet($from, $to, $sheet),
            $this->unanalysed($unanalysed),
            $this->waitingReceipts($from, $to),
            $this->unpaidSalaries($from, $to),
            $this->ledgerBalances(),
        ];

        // Worst first: what needs doing before what is fine.
        usort($checks, fn ($a, $b) => $a['ok'] <=> $b['ok']);

        return $checks;
    }

    private function sheet(Carbon $from, Carbon $to, ?Budget $sheet): array
    {
        return [
            'key' => 'sheet',
            'ok' => $sheet !== null,
            'label' => __('An Income & Expenditure sheet covers this year'),
            'detail' => $sheet
                ? __(':sheet, :from to :to.', ['sheet' => $sheet->title, 'from' => $sheet->start_date->format('d/m/Y'), 'to' => $sheet->end_date->format('d/m/Y')])
                : __('Without one, nothing says whether spending is running ahead of the year.'),
            'route' => $sheet ? null : 'admin.budget-sheet.index',
        ];
    }

    private function unanalysed(array $unanalysed): array
    {
        return [
            'key' => 'unanalysed',
            'ok' => $unanalysed['count'] === 0,
            'label' => __('Every movement has a budget line'),
            'detail' => $unanalysed['count'] === 0
                ? __('Every franc in the book is on the Income & Expenditure sheet.')
                : trans_choice('{1} :count movement worth :amount is on no budget line, so the sheet leaves it out.|[2,*] :count movements worth :amount are on no budget line, so the sheet leaves them out.', $unanalysed['count'], ['count' => $unanalysed['count'], 'amount' => number_format($unanalysed['amount'], 0)]),
            'route' => $unanalysed['count'] === 0 ? null : 'admin.account-mappings.index',
        ];
    }

    private function waitingReceipts(Carbon $from, Carbon $to): array
    {
        $receipts = DB::table('payments')->where('verification_status', 'pending')
            ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('COUNT(*) as n, COALESCE(SUM(amount), 0) as total')->first();
        $parents = DB::table('parent_payment_submissions')->where('status', 'pending')
            ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('COUNT(*) as n, COALESCE(SUM(amount), 0) as total')->first();

        $count = (int) $receipts->n + (int) $parents->n;
        $amount = (float) $receipts->total + (float) $parents->total;

        return [
            'key' => 'waiting',
            'ok' => $count === 0,
            'label' => __('No payment is waiting for approval'),
            'detail' => $count === 0
                ? __('Every payment recorded this year has been approved or turned down.')
                : trans_choice('{1} :count payment worth :amount is waiting for approval. It is not in the book until it is approved.|[2,*] :count payments worth :amount are waiting for approval. They are not in the book until they are approved.', $count, ['count' => $count, 'amount' => number_format($amount, 0)]),
            'route' => $count === 0 ? null : ((int) $parents->n > 0 ? 'admin.parent-payments.index' : 'admin.payments.index'),
        ];
    }

    private function unpaidSalaries(Carbon $from, Carbon $to): array
    {
        $unpaid = DB::table('payrolls')->where('status', Payroll::STATUS_UNPAID)
            ->whereBetween('salary_month', [$from->format('Y-m'), $to->format('Y-m')])
            ->where('salary_month', '<', now()->format('Y-m'))
            ->selectRaw('COUNT(*) as n, COALESCE(SUM(net_salary), 0) as total')->first();

        return [
            'key' => 'salaries',
            'ok' => (int) $unpaid->n === 0,
            'label' => __('Past months\' salaries are paid'),
            'detail' => (int) $unpaid->n === 0
                ? __('No payslip from a past month is still waiting to be paid.')
                : trans_choice('{1} :count payslip from a past month worth :amount is not marked paid, so the book does not show it leaving.|[2,*] :count payslips from past months worth :amount are not marked paid, so the book does not show them leaving.', (int) $unpaid->n, ['count' => (int) $unpaid->n, 'amount' => number_format((float) $unpaid->total, 0)]),
            'route' => (int) $unpaid->n === 0 ? null : 'admin.payroll.index',
        ];
    }

    private function ledgerBalances(): array
    {
        $sums = DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('e.is_posted', true)
            ->selectRaw('COALESCE(SUM(l.debit), 0) as debit, COALESCE(SUM(l.credit), 0) as credit')->first();

        $gap = abs((float) $sums->debit - (float) $sums->credit);

        return [
            'key' => 'ledger',
            'ok' => $gap < 0.5,
            'label' => __('The ledger balances'),
            'detail' => $gap < 0.5
                ? __('Debits equal credits across every posted entry.')
                : __('Posted debits and credits differ by :amount.', ['amount' => number_format($gap, 0)]),
            'route' => $gap < 0.5 ? null : 'admin.journal-entries.index',
        ];
    }
}
