<?php

namespace App\Services\EdutrustPay;

use App\Models\Branch;
use App\Models\Budget;
use App\Services\DaybookService;
use Carbon\Carbon;
use EdutrustPay\Contract\BudgetSheet;
use EdutrustPay\Contract\Money;

/**
 * One branch's budget sheet at the end of one month, as its bursar reads it.
 *
 * Built by DaybookService::monthlySummary(), the same code behind the budget sheet
 * screen here.
 *
 * THAT CODE READS EVERY BRANCH'S MONEY AT ONCE: the actuals behind it query the
 * tables directly, where the branch scope does not reach. With one branch that is
 * exactly right. With more, every campus's spending would land on each campus's
 * sheet, so the export refuses rather than send a sheet that is wrong.
 */
class BudgetSheetExporter
{
    public function __construct(private DaybookService $daybook)
    {
    }

    /**
     * @return array<string, mixed>|null  null when no budget covers the month
     */
    public function document(int $branchId, string $period, int $sequence = 1): ?array
    {
        $settings = app(SettingsResolver::class)->forBranch($branchId);

        if ($settings === null) {
            throw new \RuntimeException("Branch $branchId has no EdutrustPay credentials.");
        }

        if (Branch::query()->where('is_active', true)->count() > 1) {
            throw new \RuntimeException(
                'This system has more than one branch, and its budget sheet adds up every branch\'s money. '
                .'It is not sent, so that no campus\'s sheet carries another campus\'s spending.'
            );
        }

        $month = Carbon::createFromFormat('!Y-m', $period);

        $budget = Budget::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->where('is_institutional', true)
            ->whereDate('start_date', '<=', $month->copy()->endOfMonth())
            ->whereDate('end_date', '>=', $month)
            ->orderByDesc('id')
            ->first();

        if (! $budget) {
            return null;
        }

        $summary = $this->daybook->monthlySummary($budget, $month);
        $money = fn ($value) => Money::of(number_format((float) $value, 2, '.', ''));

        $lines = array_values(array_map(fn (array $row) => [
            'code' => $row['line']->code,
            'name' => (string) $row['line']->name,
            'section' => (string) $row['line']->section,
            'heading' => (bool) $row['line']->is_header,
            'budget' => $money($row['budget']),
            'month' => $money($row['monthly']),
            'to_date' => $money($row['cumulative']),
        ], $summary['lines']));

        if ($lines === []) {
            return null;
        }

        return BudgetSheet::build($settings['institution_ref'], $period, $sequence, gmdate('Y-m-d\TH:i:s\Z'), 'XAF', [
            'title' => (string) ($budget->title ?: $budget->budget_code ?: __('Budget')),
            'starts' => $budget->start_date->toDateString(),
            'ends' => $budget->end_date->toDateString(),
        ], $lines);
    }
}
