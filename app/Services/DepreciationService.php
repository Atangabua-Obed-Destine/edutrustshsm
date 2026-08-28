<?php

namespace App\Services;

use App\Models\AccountingPeriod;
use App\Models\DepreciationSchedule;
use App\Models\FiscalYear;
use App\Models\FixedAsset;
use App\Models\JournalEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Builds and posts depreciation schedules, and handles disposal.
 *
 * Schedules are generated MONTHLY over the asset's useful life. Two methods:
 *
 *  - straight_line: the depreciable amount spread evenly.
 *  - declining:     a fixed percentage of the REMAINING book value each year,
 *                   split monthly, switching to straight line over the
 *                   remaining life once that gives the larger charge. Pure
 *                   declining balance never reaches zero, so without the switch
 *                   an asset either stays undepreciated or needs an absurd
 *                   catch-up in the final period.
 *
 * Both stop at the salvage value — an asset is never depreciated below what it
 * is expected to be worth at the end, and rounding is absorbed by the final
 * period so the schedule sums to exactly the depreciable amount.
 */
class DepreciationService
{
    /**
     * (Re)build an asset's schedule.
     *
     * Refuses once any period has been posted: rebuilding then would silently
     * contradict entries already in the ledger.
     *
     * @return int number of periods generated
     */
    public function generateSchedule(FixedAsset $asset): int
    {
        if ($asset->schedules()->where('is_posted', true)->exists()) {
            throw new RuntimeException(__('Depreciation has already been posted for this asset; its schedule cannot be rebuilt.'));
        }

        $months = max(1, $asset->useful_life_years * 12);
        $depreciable = $asset->depreciableAmount();

        if ($depreciable <= 0) {
            throw new RuntimeException(__('This asset has nothing to depreciate: its salvage value is not below its cost.'));
        }

        $amounts = $asset->method === 'declining'
            ? $this->decliningAmounts($asset, $months, $depreciable)
            : $this->straightLineAmounts($months, $depreciable);

        return DB::transaction(function () use ($asset, $amounts) {
            $asset->schedules()->delete();

            $accumulated = 0.0;
            $start = $asset->acquisition_date->copy()->endOfMonth();

            foreach ($amounts as $index => $amount) {
                $accumulated = round($accumulated + $amount, 2);

                $asset->schedules()->create([
                    'period_number' => $index + 1,
                    'period_date' => $start->copy()->addMonthsNoOverflow($index)->endOfMonth()->toDateString(),
                    'amount' => $amount,
                    'accumulated' => $accumulated,
                    'book_value' => round((float) $asset->cost - $accumulated, 2),
                ]);
            }

            return count($amounts);
        });
    }

    /**
     * Post one period's depreciation to the ledger.
     *
     * DR depreciation expense / CR accumulated depreciation.
     */
    public function post(DepreciationSchedule $schedule): JournalEntry
    {
        if ($schedule->is_posted) {
            throw new RuntimeException(__('That period has already been posted.'));
        }

        $asset = $schedule->asset;
        $category = $asset?->category;

        $expense = $category?->depreciationAccount;
        $accumulated = $category?->accumulatedAccount;

        if (! $expense || ! $accumulated) {
            throw new RuntimeException(__('This asset category has no depreciation and accumulated-depreciation accounts configured.'));
        }

        return DB::transaction(function () use ($schedule, $asset, $expense, $accumulated) {
            $date = $schedule->period_date->toDateString();

            $entry = JournalEntry::create([
                'branch_id' => $asset->branch_id,
                'entry_number' => JournalEntry::generateEntryNumber(),
                'entry_date' => $date,
                'fiscal_year_id' => FiscalYear::active()?->id,
                'accounting_period_id' => AccountingPeriod::forDate($date)?->id,
                'journal_type' => 'general',
                'reference_type' => 'depreciation',
                'reference_id' => $schedule->id,
                'description' => __('Depreciation :period — :asset', [
                    'period' => $schedule->period_date->format('m/Y'),
                    'asset' => $asset->name,
                ]),
                'is_system_generated' => true,
            ]);

            $entry->lines()->create([
                'account_id' => $expense->id, 'line_number' => 1,
                'debit' => $schedule->amount, 'credit' => 0,
                'description' => __('Depreciation expense'),
            ]);
            $entry->lines()->create([
                'account_id' => $accumulated->id, 'line_number' => 2,
                'debit' => 0, 'credit' => $schedule->amount,
                'description' => __('Accumulated depreciation'),
            ]);

            $entry->post();

            $schedule->update(['is_posted' => true, 'journal_entry_id' => $entry->id]);

            return $entry;
        });
    }

    /**
     * Post every period that has come due across all active assets.
     *
     * @return array{posted: int, errors: array<int, string>}
     */
    public function postDue(?string $asOf = null): array
    {
        $posted = 0;
        $errors = [];

        $due = DepreciationSchedule::due($asOf)
            ->whereHas('asset', fn ($q) => $q->where('status', 'active'))
            ->with('asset.category')
            ->orderBy('period_date')
            ->get();

        foreach ($due as $schedule) {
            try {
                $this->post($schedule);
                $posted++;
            } catch (RuntimeException $e) {
                $errors[] = ($schedule->asset?->name ?? '#'.$schedule->id).': '.$e->getMessage();
            }
        }

        return ['posted' => $posted, 'errors' => $errors];
    }

    /**
     * Dispose of an asset, booking the gain or loss against book value.
     *
     * Proceeds above book value are a gain, below it a loss. Book value here is
     * net of POSTED depreciation only, so the entry agrees with the ledger
     * rather than with a schedule that may run ahead of it.
     */
    public function dispose(FixedAsset $asset, float $proceeds, string $date, ?string $note = null): JournalEntry
    {
        if ($asset->isDisposed()) {
            throw new RuntimeException(__('This asset has already been disposed of.'));
        }

        $category = $asset->category;
        $assetAccount = $category?->assetAccount;
        $accumulatedAccount = $category?->accumulatedAccount;

        if (! $assetAccount || ! $accumulatedAccount) {
            throw new RuntimeException(__('This asset category has no asset and accumulated-depreciation accounts configured.'));
        }

        $accumulated = $asset->accumulatedDepreciation();
        $bookValue = $asset->bookValue();
        $result = round($proceeds - $bookValue, 2);

        return DB::transaction(function () use ($asset, $proceeds, $date, $note, $assetAccount, $accumulatedAccount, $accumulated, $result, $category) {
            $entry = JournalEntry::create([
                'branch_id' => $asset->branch_id,
                'entry_number' => JournalEntry::generateEntryNumber(),
                'entry_date' => $date,
                'fiscal_year_id' => FiscalYear::active()?->id,
                'accounting_period_id' => AccountingPeriod::forDate($date)?->id,
                'journal_type' => 'general',
                'reference_type' => 'asset_disposal',
                'reference_id' => $asset->id,
                'description' => __('Disposal of :asset', ['asset' => $asset->name]),
                'is_system_generated' => true,
            ]);

            $line = 1;

            // Clear the asset off the books at cost, along with what has been
            // depreciated against it.
            if ($accumulated > 0) {
                $entry->lines()->create([
                    'account_id' => $accumulatedAccount->id, 'line_number' => $line++,
                    'debit' => $accumulated, 'credit' => 0,
                    'description' => __('Accumulated depreciation removed'),
                ]);
            }

            if ($proceeds > 0) {
                $cash = $this->proceedsAccount();
                $entry->lines()->create([
                    'account_id' => $cash->id, 'line_number' => $line++,
                    'debit' => $proceeds, 'credit' => 0,
                    'description' => __('Disposal proceeds'),
                ]);
            }

            $entry->lines()->create([
                'account_id' => $assetAccount->id, 'line_number' => $line++,
                'debit' => 0, 'credit' => $asset->cost,
                'description' => __('Asset cost removed'),
            ]);

            if (abs($result) >= 0.01) {
                $resultAccount = $result > 0
                    ? $this->gainAccount()
                    : ($category->depreciationAccount ?? $this->lossAccount());

                $entry->lines()->create([
                    'account_id' => $resultAccount->id, 'line_number' => $line++,
                    'debit' => $result < 0 ? abs($result) : 0,
                    'credit' => $result > 0 ? $result : 0,
                    'description' => $result > 0 ? __('Gain on disposal') : __('Loss on disposal'),
                ]);
            }

            $entry->post();

            $asset->update([
                'status' => 'disposed',
                'disposal_date' => $date,
                'disposal_amount' => $proceeds,
                'disposal_note' => $note,
            ]);

            // Future periods are void once the asset is gone.
            $asset->schedules()->where('is_posted', false)->delete();

            return $entry->fresh('lines');
        });
    }

    /**
     * Straight line: the depreciable amount spread evenly, with rounding
     * absorbed by the final period so the total is exact.
     *
     * @return array<int, float>
     */
    private function straightLineAmounts(int $months, float $depreciable): array
    {
        $perMonth = floor($depreciable / $months * 100) / 100;
        $amounts = array_fill(0, $months, $perMonth);
        $amounts[$months - 1] = round($depreciable - $perMonth * ($months - 1), 2);

        return $amounts;
    }

    /**
     * Declining balance, switching to straight line when that becomes larger.
     *
     * Pure declining balance never reaches zero — it takes a percentage of an
     * ever-smaller base — so it would either leave the asset undepreciated or
     * need a huge catch-up charge in the final period, which is neither
     * front-loaded nor defensible. The standard treatment is to switch to
     * straight line over the REMAINING life once that gives the bigger charge.
     * The result stays front-loaded and still sums exactly to the depreciable
     * amount.
     *
     * @return array<int, float>
     */
    private function decliningAmounts(FixedAsset $asset, int $months, float $depreciable): array
    {
        $rate = (float) ($asset->declining_rate ?: (200 / max(1, $asset->useful_life_years)));
        $remaining = $depreciable;
        $amounts = [];

        for ($month = 0; $month < $months; $month++) {
            $monthsLeft = $months - $month;

            $declining = $remaining * ($rate / 100) / 12;
            $straightLine = $remaining / $monthsLeft;

            $amount = round(max($declining, $straightLine), 2);

            // Never take the asset below its salvage value, and let the final
            // period absorb whatever rounding has left behind.
            $amount = $monthsLeft === 1 ? round($remaining, 2) : min($amount, round($remaining, 2));

            $amounts[] = max(0, $amount);
            $remaining = round($remaining - $amount, 2);
        }

        return $amounts;
    }

    private function proceedsAccount(): \App\Models\ChartOfAccount
    {
        return $this->accountByCode(['571', '521', '57', '52'], __('Cash'));
    }

    private function gainAccount(): \App\Models\ChartOfAccount
    {
        return $this->accountByCode(['758', '75', '70'], __('Other Income'));
    }

    private function lossAccount(): \App\Models\ChartOfAccount
    {
        return $this->accountByCode(['658', '65', '68'], __('Miscellaneous Expenses'));
    }

    /** @param array<int, string> $codes */
    private function accountByCode(array $codes, string $label): \App\Models\ChartOfAccount
    {
        foreach ($codes as $code) {
            if ($account = \App\Models\ChartOfAccount::where('account_code', $code)->first()) {
                return $account;
            }
        }

        throw new RuntimeException(__('The :account account is missing from the chart of accounts.', ['account' => $label]));
    }
}
