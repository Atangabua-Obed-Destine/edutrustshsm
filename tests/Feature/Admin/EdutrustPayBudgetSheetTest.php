<?php

namespace Tests\Feature\Admin;

use App\Models\Branch;
use App\Models\Budget;
use App\Models\BudgetAllocation;
use App\Models\BudgetLine;
use App\Models\EdutrustPaySetting;
use App\Services\EdutrustPay\BudgetSheetExporter;
use EdutrustPay\Contract\BudgetSheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sending the budget sheet: the bursar's own sheet, and never one that mixes campuses.
 */
class EdutrustPayBudgetSheetTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-07 10:00:00');
        $this->branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'ST JEROME', 'is_active' => true]);

        EdutrustPaySetting::create([
            'branch_id' => $this->branch->id,
            'enabled' => true,
            'endpoint' => 'https://console.example.test',
            'institution_ref' => '11111111-1111-4111-8111-111111111111',
            'key_id' => 'etp_test',
            'secret_ciphertext' => 'a-test-secret-of-sufficient-length',
        ]);
    }

    public function test_the_sheet_carries_the_budget_for_each_line(): void
    {
        $this->budget(allocate: 600000);

        $sheet = app(BudgetSheetExporter::class)->document($this->branch->id, '2026-10');

        $this->assertSame([], BudgetSheet::errors($sheet));
        $this->assertSame('2026/2027', $sheet['budget']['title']);

        $line = collect($sheet['lines'])->firstWhere('name', 'Test fuel');
        $this->assertSame('600000.00', $line['budget']);
        $this->assertSame('0.00', $line['to_date']);
    }

    public function test_a_month_no_budget_covers_is_not_sent(): void
    {
        $this->budget(allocate: 600000);

        $this->assertNull(app(BudgetSheetExporter::class)->document($this->branch->id, '2025-10'));
    }

    public function test_it_refuses_once_there_is_a_second_campus(): void
    {
        $this->budget(allocate: 600000);
        Branch::create(['code' => 'OTHER', 'name' => 'OTHER CAMPUS', 'is_active' => true]);

        $this->expectExceptionMessage('more than one branch');

        app(BudgetSheetExporter::class)->document($this->branch->id, '2026-10');
    }

    private function budget(int $allocate): void
    {
        $budget = Budget::create([
            'branch_id' => $this->branch->id,
            'title' => '2026/2027',
            'budget_code' => 'B-2026',
            'type' => 'annual',
            'fiscal_year' => '2026',
            'is_institutional' => true,
            'start_date' => '2026-09-01',
            'end_date' => '2027-08-31',
            'total_amount' => $allocate,
            'status' => 'draft',
        ]);

        $line = BudgetLine::create([
            'branch_id' => $this->branch->id,
            'code' => 'T.1',
            'name' => 'Test fuel',
            'section' => 'expenditure',
            'is_header' => false,
            'sort_order' => 9999,
            'status' => true,
        ]);

        BudgetAllocation::create([
            'branch_id' => $this->branch->id,
            'budget_id' => $budget->id,
            'budget_line_id' => $line->id,
            'title' => 'Fuel',
            'allocated_amount' => $allocate,
            'spent_amount' => 0,
            'remaining_amount' => $allocate,
        ]);
    }
}
