<?php

namespace Tests\Feature\Finance;

use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\FiscalYear;
use App\Models\FixedAsset;
use App\Models\FixedAssetCategory;
use App\Models\User;
use App\Services\DepreciationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fixed assets could be registered, depreciated and disposed, but not corrected,
 * deleted or reported on. The reference system edits, deletes and reports on
 * them; this follows that, with one difference: financial terms can still be
 * corrected until depreciation reaches the ledger.
 */
class FixedAssetManagementTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private FixedAssetCategory $category;
    private FixedAssetCategory $otherCategory;

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
            $first = now()->setDate(2026, $m, 1);
            AccountingPeriod::create([
                'branch_id' => $this->branch->id, 'fiscal_year_id' => $fy->id,
                'name' => $first->format('F Y'), 'period_number' => $m,
                'start_date' => $first->toDateString(), 'end_date' => $first->copy()->endOfMonth()->toDateString(),
                'is_closed' => false,
            ]);
        }

        $account = fn (string $code, int $class, string $normal) => ChartOfAccount::create([
            'branch_id' => $this->branch->id, 'account_code' => $code, 'account_name' => 'Account '.$code,
            'class_number' => $class, 'account_type' => 'detail', 'account_category' => 'detail',
            'normal_balance' => $normal, 'is_active' => true,
        ]);

        $asset = $account('244', 2, 'debit');
        $expense = $account('681', 6, 'debit');
        $accumulated = $account('284', 2, 'credit');

        $this->category = FixedAssetCategory::create([
            'branch_id' => $this->branch->id, 'name' => 'Furniture', 'useful_life_years' => 5,
            'method' => 'straight_line', 'asset_account_id' => $asset->id,
            'depreciation_account_id' => $expense->id, 'accumulated_account_id' => $accumulated->id, 'is_active' => true,
        ]);
        $this->otherCategory = FixedAssetCategory::create([
            'branch_id' => $this->branch->id, 'name' => 'Computers', 'useful_life_years' => 3,
            'method' => 'straight_line', 'asset_account_id' => $asset->id,
            'depreciation_account_id' => $expense->id, 'accumulated_account_id' => $accumulated->id, 'is_active' => true,
        ]);
    }

    private function asset(array $attrs = []): FixedAsset
    {
        $asset = FixedAsset::create(array_merge([
            'branch_id' => $this->branch->id, 'fixed_asset_category_id' => $this->category->id,
            'code' => 'FA-001', 'name' => 'Staff room tables', 'acquisition_date' => '2026-01-01',
            'cost' => 120000, 'salvage_value' => 0, 'useful_life_years' => 1,
            'method' => 'straight_line', 'status' => 'active',
        ], $attrs));

        app(DepreciationService::class)->generateSchedule($asset);

        return $asset->fresh();
    }

    /** @return array<string, mixed> */
    private function form(FixedAsset $asset, array $overrides = []): array
    {
        return array_merge([
            'fixed_asset_category_id' => $asset->fixed_asset_category_id,
            'code' => $asset->code,
            'name' => $asset->name,
            'acquisition_date' => $asset->acquisition_date->toDateString(),
            'cost' => (float) $asset->cost,
            'salvage_value' => (float) $asset->salvage_value,
            'useful_life_years' => $asset->useful_life_years,
            'method' => $asset->method,
        ], $overrides);
    }

    private function postFirstPeriod(FixedAsset $asset): void
    {
        app(DepreciationService::class)->post($asset->schedules()->orderBy('period_number')->firstOrFail());
    }

    // ------------------------------------------------------------ editing

    public function test_the_edit_screen_renders(): void
    {
        $this->get(route('admin.fixed-assets.edit', $this->asset()))->assertSuccessful();
    }

    public function test_descriptive_fields_can_always_change(): void
    {
        $asset = $this->asset();
        $this->postFirstPeriod($asset);

        $this->put(route('admin.fixed-assets.update', $asset), $this->form($asset, [
            'name' => 'Staff room tables (oak)', 'location' => 'Block B',
        ]))->assertRedirect(route('admin.fixed-assets.schedule', $asset))->assertSessionHas('success');

        $this->assertSame('Staff room tables (oak)', $asset->fresh()->name);
        $this->assertSame('Block B', $asset->fresh()->location);
    }

    public function test_financial_terms_can_be_corrected_before_anything_is_posted(): void
    {
        $asset = $this->asset();
        $this->assertEquals(10000, (float) $asset->schedules()->first()->amount);

        $this->put(route('admin.fixed-assets.update', $asset), $this->form($asset, ['cost' => 240000]))
            ->assertSessionHas('success');

        // The schedule is rebuilt from the corrected cost.
        $this->assertEquals(240000, (float) $asset->fresh()->cost);
        $this->assertEquals(20000, (float) $asset->schedules()->first()->amount);
        $this->assertEquals(240000, (float) $asset->schedules()->sum('amount'));
    }

    public function test_financial_terms_are_fixed_once_depreciation_is_posted(): void
    {
        $asset = $this->asset();
        $this->postFirstPeriod($asset);

        $this->put(route('admin.fixed-assets.update', $asset), $this->form($asset, ['cost' => 240000]))
            ->assertSessionHas('error');

        $this->assertEquals(120000, (float) $asset->fresh()->cost);
        $this->assertSame(1, $asset->schedules()->where('is_posted', true)->count());
    }

    public function test_the_category_is_fixed_once_depreciation_is_posted(): void
    {
        $asset = $this->asset();
        $this->postFirstPeriod($asset);

        // The category decides which accounts later periods post to.
        $this->put(route('admin.fixed-assets.update', $asset), $this->form($asset, [
            'fixed_asset_category_id' => $this->otherCategory->id,
        ]))->assertSessionHas('error');

        $this->assertSame($this->category->id, $asset->fresh()->fixed_asset_category_id);
    }

    public function test_a_disposed_asset_cannot_be_edited(): void
    {
        $asset = $this->asset();
        // The edit guard is under test, not the disposal posting.
        $asset->update(['status' => 'disposed', 'disposal_date' => '2026-02-15', 'disposal_amount' => 50000]);

        $this->put(route('admin.fixed-assets.update', $asset->fresh()), $this->form($asset->fresh(), ['name' => 'Renamed']))
            ->assertSessionHas('error');

        $this->assertNotSame('Renamed', $asset->fresh()->name);
    }

    // ------------------------------------------------------------ deleting

    public function test_an_asset_with_nothing_posted_can_be_deleted(): void
    {
        $asset = $this->asset();

        $this->delete(route('admin.fixed-assets.destroy', $asset))
            ->assertRedirect(route('admin.fixed-assets.index'));

        $this->assertNull(FixedAsset::find($asset->id));
    }

    public function test_an_asset_with_posted_depreciation_cannot_be_deleted(): void
    {
        $asset = $this->asset();
        $this->postFirstPeriod($asset);

        $this->delete(route('admin.fixed-assets.destroy', $asset))->assertSessionHas('error');

        $this->assertNotNull(FixedAsset::find($asset->id));
    }

    // ------------------------------------------------------------ reports

    public function test_the_register_shows_depreciation_to_date(): void
    {
        $asset = $this->asset();
        $this->postFirstPeriod($asset);  // January, 10,000

        $response = $this->get(route('admin.fixed-assets.register', ['as_of' => '2026-06-30']))->assertSuccessful();

        $row = $response->viewData('rows')->first();
        $this->assertEquals(10000, $row->accumulated);
        $this->assertEquals(110000, $row->book_value);

        // Depreciation posted after the as-of date is not counted.
        $this->assertEquals(0, $this->get(route('admin.fixed-assets.register', ['as_of' => '2026-01-15']))
            ->viewData('rows')->first()->accumulated);
    }

    public function test_the_register_leaves_out_assets_acquired_later(): void
    {
        $this->asset();
        $this->asset(['code' => 'FA-002', 'acquisition_date' => '2026-09-01']);

        $this->assertCount(1, $this->get(route('admin.fixed-assets.register', ['as_of' => '2026-06-30']))->viewData('rows'));
    }

    public function test_the_depreciation_report_splits_posted_from_pending(): void
    {
        $asset = $this->asset();
        $this->postFirstPeriod($asset);

        $response = $this->get(route('admin.fixed-assets.depreciation-report', ['from' => '2026-01-01', 'to' => '2026-03-31']))
            ->assertSuccessful();

        $totals = $response->viewData('totals');
        $this->assertEquals(10000, $totals['posted']);
        $this->assertEquals(20000, $totals['pending']);

        $category = $response->viewData('byCategory')->first();
        $this->assertSame('Furniture', $category->name);
        $this->assertSame(1, $category->assets);
    }

    public function test_both_reports_export_to_csv(): void
    {
        $this->asset();

        $register = $this->get(route('admin.fixed-assets.register', ['export' => 'csv']))->assertSuccessful()->streamedContent();
        $this->assertStringContainsString('FA-001', $register);

        $report = $this->get(route('admin.fixed-assets.depreciation-report', ['from' => '2026-01-01', 'to' => '2026-12-31', 'export' => 'csv']))
            ->assertSuccessful()->streamedContent();
        $this->assertStringContainsString('Staff room tables', $report);
    }

    public function test_editing_needs_the_permission(): void
    {
        $asset = $this->asset();

        $clerk = User::create([
            'first_name' => 'Cal', 'last_name' => 'Clerk',
            'email' => 'clerk@example.test', 'password' => 'password',
            'role' => 'staff', 'is_active' => true,
        ]);
        $clerk->branches()->attach($this->branch->id, ['is_default' => true]);

        $this->actingAs($clerk)->put(route('admin.fixed-assets.update', $asset), $this->form($asset, ['name' => 'X']))->assertForbidden();
        $this->actingAs($clerk)->delete(route('admin.fixed-assets.destroy', $asset))->assertForbidden();
    }
}
