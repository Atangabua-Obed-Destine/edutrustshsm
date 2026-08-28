<?php

namespace Tests\Feature\Finance;

use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'is_active' => true]);
    }

    private function fiscalYear(bool $closed = false): FiscalYear
    {
        return FiscalYear::create([
            'branch_id' => $this->branch->id, 'name' => 'FY2026',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
            'is_active' => true, 'is_closed' => $closed,
        ]);
    }

    private function period(FiscalYear $fy, bool $closed): AccountingPeriod
    {
        return AccountingPeriod::create([
            'branch_id' => $this->branch->id, 'fiscal_year_id' => $fy->id,
            'name' => 'January 2026', 'period_number' => 1,
            'start_date' => '2026-01-01', 'end_date' => '2026-01-31',
            'is_closed' => $closed,
        ]);
    }

    private function account(string $code, int $class, string $normal): ChartOfAccount
    {
        return ChartOfAccount::create([
            'branch_id' => $this->branch->id, 'account_code' => $code,
            'account_name' => 'Account '.$code, 'class_number' => $class,
            'account_type' => 'detail', 'normal_balance' => $normal,
            'is_active' => true,
        ]);
    }

    public function test_for_date_returns_a_closed_period_so_posting_can_refuse_it(): void
    {
        $fy = $this->fiscalYear();
        $closed = $this->period($fy, closed: true);

        // Regression: this used to filter is_closed = false and return null, which
        // left accounting_period_id NULL and silently bypassed the guard below.
        $this->assertNotNull(AccountingPeriod::forDate('2026-01-15'));
        $this->assertSame($closed->id, AccountingPeriod::forDate('2026-01-15')->id);
    }

    public function test_posting_into_a_closed_period_is_refused(): void
    {
        $fy = $this->fiscalYear();
        $period = $this->period($fy, closed: true);
        $debit = $this->account('601', 6, 'debit');
        $credit = $this->account('521', 5, 'credit');

        $entry = JournalEntry::create([
            'branch_id' => $this->branch->id,
            'entry_number' => 'JE-2026-0001', 'entry_date' => '2026-01-15',
            'fiscal_year_id' => $fy->id, 'accounting_period_id' => $period->id,
            'journal_type' => 'general', 'description' => 'Back-dated',
        ]);
        $entry->lines()->create(['account_id' => $debit->id, 'line_number' => 1, 'debit' => 100, 'credit' => 0]);
        $entry->lines()->create(['account_id' => $credit->id, 'line_number' => 2, 'debit' => 0, 'credit' => 100]);

        $this->expectException(\RuntimeException::class);
        $entry->post();
    }

    public function test_an_unbalanced_entry_cannot_be_posted(): void
    {
        $fy = $this->fiscalYear();
        $period = $this->period($fy, closed: false);
        $debit = $this->account('601', 6, 'debit');
        $credit = $this->account('521', 5, 'credit');

        $entry = JournalEntry::create([
            'branch_id' => $this->branch->id,
            'entry_number' => 'JE-2026-0002', 'entry_date' => '2026-01-15',
            'fiscal_year_id' => $fy->id, 'accounting_period_id' => $period->id,
            'journal_type' => 'general', 'description' => 'Lopsided',
        ]);
        $entry->lines()->create(['account_id' => $debit->id, 'line_number' => 1, 'debit' => 100, 'credit' => 0]);
        $entry->lines()->create(['account_id' => $credit->id, 'line_number' => 2, 'debit' => 0, 'credit' => 60]);

        $this->expectException(\RuntimeException::class);
        $entry->post();
    }

    public function test_a_balanced_entry_in_an_open_period_posts(): void
    {
        $fy = $this->fiscalYear();
        $period = $this->period($fy, closed: false);
        $debit = $this->account('601', 6, 'debit');
        $credit = $this->account('521', 5, 'credit');

        $entry = JournalEntry::create([
            'branch_id' => $this->branch->id,
            'entry_number' => 'JE-2026-0003', 'entry_date' => '2026-01-15',
            'fiscal_year_id' => $fy->id, 'accounting_period_id' => $period->id,
            'journal_type' => 'general', 'description' => 'Good entry',
        ]);
        $entry->lines()->create(['account_id' => $debit->id, 'line_number' => 1, 'debit' => 100, 'credit' => 0]);
        $entry->lines()->create(['account_id' => $credit->id, 'line_number' => 2, 'debit' => 0, 'credit' => 100]);

        $this->assertTrue($entry->post());
        $this->assertTrue($entry->fresh()->is_posted);
    }
}
