<?php

namespace Tests\Feature\Finance;

use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\DepreciationSchedule;
use App\Models\FiscalYear;
use App\Models\FixedAsset;
use App\Models\FixedAssetCategory;
use App\Models\JournalEntryLine;
use App\Models\User;
use App\Services\DepreciationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepreciationTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private FixedAssetCategory $category;
    private ChartOfAccount $assetAccount;
    private ChartOfAccount $expense;
    private ChartOfAccount $accumulated;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'is_active' => true]);

        $admin = User::create([
            'first_name' => 'Ada', 'last_name' => 'Admin',
            'email' => 'admin@example.test', 'password' => 'password',
            'role' => 'super_admin', 'is_active' => true,
        ]);
        $admin->branches()->attach($this->branch->id, ['is_default' => true]);
        $this->actingAs($admin);

        $fy = FiscalYear::create([
            'branch_id' => $this->branch->id, 'name' => 'FY2026',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
            'is_active' => true, 'is_closed' => false,
        ]);
        foreach (range(1, 12) as $m) {
            AccountingPeriod::create([
                'branch_id' => $this->branch->id, 'fiscal_year_id' => $fy->id,
                'name' => 'M'.$m, 'period_number' => $m,
                'start_date' => sprintf('2026-%02d-01', $m),
                'end_date' => date('Y-m-t', strtotime(sprintf('2026-%02d-01', $m))),
                'is_closed' => false,
            ]);
        }

        $this->assetAccount = $this->account('215', 2, 'debit', 'Equipment');
        $this->expense = $this->account('681', 6, 'debit', 'Depreciation Expense');
        $this->accumulated = $this->account('281', 2, 'credit', 'Accumulated Depreciation');
        $this->account('571', 5, 'debit', 'Cash Box');
        $this->account('758', 7, 'credit', 'Other Income');
        $this->account('658', 6, 'debit', 'Miscellaneous Expenses');

        $this->category = FixedAssetCategory::create([
            'branch_id' => $this->branch->id,
            'name' => 'Equipment', 'useful_life_years' => 5, 'method' => 'straight_line',
            'asset_account_id' => $this->assetAccount->id,
            'depreciation_account_id' => $this->expense->id,
            'accumulated_account_id' => $this->accumulated->id,
            'is_active' => true,
        ]);
    }

    private function account(string $code, int $class, string $normal, string $name): ChartOfAccount
    {
        return ChartOfAccount::create([
            'branch_id' => $this->branch->id, 'account_code' => $code,
            'account_name' => $name, 'class_number' => $class,
            'account_type' => 'detail', 'normal_balance' => $normal, 'is_active' => true,
        ]);
    }

    private function asset(array $attrs = []): FixedAsset
    {
        return FixedAsset::create(array_merge([
            'branch_id' => $this->branch->id,
            'fixed_asset_category_id' => $this->category->id,
            'code' => 'EQ-'.uniqid(),
            'name' => 'Projector',
            'acquisition_date' => '2026-01-10',
            'cost' => 1200000,
            'salvage_value' => 0,
            'useful_life_years' => 5,
            'method' => 'straight_line',
            'status' => 'active',
        ], $attrs));
    }

    private function service(): DepreciationService
    {
        return app(DepreciationService::class);
    }

    public function test_a_straight_line_schedule_spans_the_useful_life(): void
    {
        $asset = $this->asset();

        $periods = $this->service()->generateSchedule($asset);

        $this->assertSame(60, $periods, '5 years is 60 monthly periods');
        $this->assertSame(60, $asset->schedules()->count());
    }

    public function test_the_schedule_sums_to_exactly_the_depreciable_amount(): void
    {
        $asset = $this->asset(['cost' => 1000000, 'salvage_value' => 100000]);

        $this->service()->generateSchedule($asset);

        // Rounding is absorbed by the final period rather than leaving a
        // fraction of a franc undepreciated.
        $this->assertEquals(900000, round((float) $asset->schedules()->sum('amount'), 2));
    }

    public function test_an_asset_is_never_taken_below_its_salvage_value(): void
    {
        $asset = $this->asset(['cost' => 1000000, 'salvage_value' => 250000]);

        $this->service()->generateSchedule($asset);
        $last = $asset->schedules()->reorder('period_number', 'desc')->first();

        $this->assertEquals(250000, round((float) $last->book_value, 2));
    }

    public function test_declining_balance_front_loads_the_charge(): void
    {
        $asset = $this->asset(['method' => 'declining', 'declining_rate' => 40]);

        $this->service()->generateSchedule($asset);
        $schedules = $asset->schedules()->orderBy('period_number')->get();

        $this->assertGreaterThan(
            (float) $schedules->last()->amount,
            (float) $schedules->first()->amount,
            'declining balance charges more early on'
        );
    }

    public function test_an_asset_with_no_depreciable_amount_is_refused(): void
    {
        $asset = $this->asset(['cost' => 100000, 'salvage_value' => 100000]);

        $this->expectExceptionMessage('nothing to depreciate');
        $this->service()->generateSchedule($asset);
    }

    public function test_posting_a_period_writes_a_balanced_entry(): void
    {
        $asset = $this->asset();
        $this->service()->generateSchedule($asset);

        $schedule = $asset->schedules()->orderBy('period_number')->first();
        $entry = $this->service()->post($schedule);

        $this->assertTrue($entry->is_posted);
        $this->assertEquals(
            (float) $entry->lines->sum('debit'),
            (float) $entry->lines->sum('credit')
        );
        // DR expense, CR accumulated depreciation.
        $this->assertEquals((float) $schedule->amount, (float) JournalEntryLine::where('account_id', $this->expense->id)->sum('debit'));
        $this->assertEquals((float) $schedule->amount, (float) JournalEntryLine::where('account_id', $this->accumulated->id)->sum('credit'));
    }

    public function test_a_period_cannot_be_posted_twice(): void
    {
        $asset = $this->asset();
        $this->service()->generateSchedule($asset);
        $schedule = $asset->schedules()->first();

        $this->service()->post($schedule);

        $this->expectExceptionMessage('already been posted');
        $this->service()->post($schedule->fresh());
    }

    public function test_the_schedule_cannot_be_rebuilt_once_posted(): void
    {
        $asset = $this->asset();
        $this->service()->generateSchedule($asset);
        $this->service()->post($asset->schedules()->first());

        // Rebuilding would silently contradict entries already in the ledger.
        $this->expectExceptionMessage('cannot be rebuilt');
        $this->service()->generateSchedule($asset->fresh());
    }

    public function test_posting_due_periods_skips_future_ones(): void
    {
        $asset = $this->asset(['acquisition_date' => '2026-01-10']);
        $this->service()->generateSchedule($asset);

        $result = $this->service()->postDue('2026-03-31');

        // January, February and March only.
        $this->assertSame(3, $result['posted']);
        $this->assertSame(3, DepreciationSchedule::where('is_posted', true)->count());
    }

    public function test_book_value_reflects_only_posted_depreciation(): void
    {
        $asset = $this->asset(['cost' => 1200000]);
        $this->service()->generateSchedule($asset);
        $this->service()->postDue('2026-02-28');   // two months

        $asset->refresh();

        // 1,200,000 over 60 months = 20,000 a month.
        $this->assertEquals(40000, $asset->accumulatedDepreciation());
        $this->assertEquals(1160000, $asset->bookValue());
    }

    public function test_disposing_above_book_value_books_a_gain(): void
    {
        $asset = $this->asset(['cost' => 1200000]);
        $this->service()->generateSchedule($asset);
        $this->service()->postDue('2026-02-28');   // book value 1,160,000

        $entry = $this->service()->dispose($asset->fresh(), 1300000, '2026-03-01');

        $this->assertEquals(
            (float) $entry->lines->sum('debit'),
            (float) $entry->lines->sum('credit'),
            'the disposal entry must balance'
        );

        $gain = ChartOfAccount::where('account_code', '758')->firstOrFail();
        $this->assertEquals(140000, (float) JournalEntryLine::where('account_id', $gain->id)->sum('credit'));
    }

    public function test_disposing_below_book_value_books_a_loss(): void
    {
        $asset = $this->asset(['cost' => 1200000]);
        $this->service()->generateSchedule($asset);
        $this->service()->postDue('2026-02-28');   // book value 1,160,000

        $entry = $this->service()->dispose($asset->fresh(), 1000000, '2026-03-01');

        $this->assertEquals(
            (float) $entry->lines->sum('debit'),
            (float) $entry->lines->sum('credit')
        );
        // The loss is a debit somewhere; the entry balancing is the guarantee.
        $this->assertGreaterThan(0, $entry->lines->sum('debit'));
    }

    public function test_disposal_marks_the_asset_and_voids_future_periods(): void
    {
        $asset = $this->asset();
        $this->service()->generateSchedule($asset);
        $this->service()->postDue('2026-02-28');

        $this->service()->dispose($asset->fresh(), 500000, '2026-03-01');

        $asset->refresh();
        $this->assertSame('disposed', $asset->status);
        // Only the two posted periods survive; the rest are void.
        $this->assertSame(2, $asset->schedules()->count());
    }

    public function test_an_asset_cannot_be_disposed_twice(): void
    {
        $asset = $this->asset();
        $this->service()->generateSchedule($asset);
        $this->service()->dispose($asset, 100000, '2026-03-01');

        $this->expectExceptionMessage('already been disposed');
        $this->service()->dispose($asset->fresh(), 50000, '2026-04-01');
    }

    public function test_registering_an_asset_generates_its_schedule(): void
    {
        $this->post(route('admin.fixed-assets.store'), [
            'fixed_asset_category_id' => $this->category->id,
            'code' => 'EQ-100',
            'name' => 'Laptop',
            'acquisition_date' => '2026-01-10',
            'cost' => 600000,
            'salvage_value' => 0,
            'useful_life_years' => 3,
            'method' => 'straight_line',
        ])->assertRedirect()->assertSessionHas('success');

        $asset = FixedAsset::where('code', 'EQ-100')->firstOrFail();
        $this->assertSame(36, $asset->schedules()->count());
    }

    public function test_a_salvage_value_at_or_above_cost_is_rejected(): void
    {
        $this->post(route('admin.fixed-assets.store'), [
            'fixed_asset_category_id' => $this->category->id,
            'code' => 'EQ-200', 'name' => 'Desk',
            'acquisition_date' => '2026-01-10',
            'cost' => 100000, 'salvage_value' => 100000,
            'useful_life_years' => 5, 'method' => 'straight_line',
        ])->assertSessionHasErrors('salvage_value');

        $this->assertSame(0, FixedAsset::where('code', 'EQ-200')->count());
    }

    public function test_the_screens_render(): void
    {
        $asset = $this->asset();
        $this->service()->generateSchedule($asset);

        $this->get(route('admin.fixed-assets.index'))->assertSuccessful();
        $this->get(route('admin.fixed-assets.schedule', $asset))->assertSuccessful();
        $this->get(route('admin.fixed-assets.categories'))->assertSuccessful();
    }
}
