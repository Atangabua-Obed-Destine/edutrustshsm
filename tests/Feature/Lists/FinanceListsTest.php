<?php

namespace Tests\Feature\Lists;

use App\Models\Branch;
use App\Models\Budget;
use App\Models\ChartOfAccount;
use App\Models\Department;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FixedAsset;
use App\Models\FixedAssetCategory;
use App\Models\Income;
use App\Models\IncomeCategory;
use App\Models\JournalEntry;
use App\Models\PaymentAccount;
use App\Models\PaymentAccountTransaction;
use App\Models\PaymentAccountTransfer;
use App\Models\PaymentAccountType;
use App\Models\RecurringJournalEntry;
use App\Models\SchoolSetting;
use App\Models\TaxGroup;
use App\Models\TaxSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The finance lists on the shared list toolbar (App\Support\Listing): each one
 * opens inside its redraw region, sorts where a heading offers it, and exports
 * every filtered row with amounts as plain numbers.
 */
class FinanceListsTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'is_active' => true]);
        SchoolSetting::create(['branch_id' => $this->branch->id, 'school_name' => 'Saint Joseph College', 'school_code' => 'SJC', 'school_level_mode' => 'secondary', 'pass_mark' => 10, 'max_mark' => 20]);
        $this->admin = User::create(['first_name' => 'Ada', 'last_name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password', 'role' => 'super_admin', 'is_active' => true]);
        $this->admin->branches()->attach($this->branch->id, ['is_default' => true]);

        $this->actingAs($this->admin);
    }

    /** The CSV's lines, without the byte-order mark and blank lines. */
    private function csv(TestResponse $response): array
    {
        $response->assertOk();
        $body = str_replace(["\xEF\xBB\xBF", "\r"], '', $response->streamedContent());

        return array_values(array_filter(explode("\n", $body)));
    }

    private function account(string $title): PaymentAccount
    {
        $type = PaymentAccountType::firstOrCreate(['slug' => 'cash'], ['branch_id' => $this->branch->id, 'title' => 'Cash', 'status' => true]);

        return PaymentAccount::create([
            'branch_id' => $this->branch->id, 'title' => $title, 'account_type_id' => $type->id,
            'opening_balance' => 0, 'current_balance' => 0, 'status' => true,
        ]);
    }

    private function txn(PaymentAccount $account, string $type, float $amount, string $date, float $balance, string $title): PaymentAccountTransaction
    {
        return PaymentAccountTransaction::create([
            'branch_id' => $this->branch->id, 'payment_account_id' => $account->id,
            'transaction_type' => $type, 'amount' => $amount, 'transaction_date' => $date,
            'title' => $title, 'balance_after' => $balance,
        ]);
    }

    private function chartAccount(string $code, int $class): ChartOfAccount
    {
        return ChartOfAccount::create([
            'branch_id' => $this->branch->id, 'account_code' => $code, 'account_name' => 'Account '.$code,
            'class_number' => $class, 'account_type' => 'detail', 'normal_balance' => 'debit', 'is_active' => true,
        ]);
    }

    // ── Income ───────────────────────────────────────────────────────────

    public function test_income_list(): void
    {
        $canteen = IncomeCategory::create(['branch_id' => $this->branch->id, 'title' => 'Canteen', 'slug' => 'canteen', 'status' => true]);
        foreach ([['Sales A', 1500.5, 3], ['Sales B', 250000, 2], ['Rent C', 80, 1]] as [$title, $amount, $daysAgo]) {
            Income::create([
                'branch_id' => $this->branch->id, 'category_id' => $canteen->id, 'title' => $title,
                'amount' => $amount, 'date' => now()->subDays($daysAgo)->toDateString(), 'status' => true,
            ]);
        }

        $page = $this->get(route('admin.account.income.index'))->assertOk()
            ->assertSee('data-list-region="incomes"', false);
        $this->assertSame('Rent C', $page->viewData('incomes')->first()->title, 'newest date first');
        $this->assertEquals(251580.5, $page->viewData('total'), 'the filtered total is unchanged');

        $byAmount = $this->get(route('admin.account.income.index', ['sort' => 'amount', 'dir' => 'desc']));
        $this->assertSame('Sales B', $byAmount->viewData('incomes')->first()->title);

        $lines = $this->csv($this->get(route('admin.account.income.index', ['title' => 'Sales', 'sort' => 'amount', 'export' => 'csv'])));
        $this->assertCount(1 + 2, $lines);
        $this->assertStringContainsString(__('Amount'), $lines[0]);
        $this->assertStringContainsString('"Sales A",Canteen,,1500.5,', $lines[1]);
        $this->assertStringContainsString('"Sales B",Canteen,,250000,', $lines[2]);
    }

    // ── Expense ──────────────────────────────────────────────────────────

    public function test_expense_list(): void
    {
        $chalk = ExpenseCategory::create(['branch_id' => $this->branch->id, 'title' => 'Teaching materials', 'slug' => 'teaching', 'status' => true]);
        foreach ([['Chalk', 12500.75, 2], ['Diesel', 40000, 1]] as [$title, $amount, $daysAgo]) {
            Expense::create([
                'branch_id' => $this->branch->id, 'category_id' => $chalk->id, 'title' => $title,
                'amount' => $amount, 'date' => now()->subDays($daysAgo)->toDateString(), 'status' => true,
                'approval_status' => 'approved',
            ]);
        }

        $page = $this->get(route('admin.account.expense.index'))->assertOk()
            ->assertSee('data-list-region="expenses"', false);
        $this->assertSame('Diesel', $page->viewData('expenses')->first()->title);

        $byTitle = $this->get(route('admin.account.expense.index', ['sort' => 'title']));
        $this->assertSame('Chalk', $byTitle->viewData('expenses')->first()->title);

        $lines = $this->csv($this->get(route('admin.account.expense.index', ['export' => 'csv'])));
        $this->assertCount(1 + 2, $lines);
        $this->assertStringContainsString('Diesel,"Teaching materials",,40000,', $lines[1]);
        $this->assertStringContainsString('Chalk,"Teaching materials",,12500.75,', $lines[2]);
    }

    // ── Account book (a running-balance ledger: fixed order) ────────────

    public function test_account_book_keeps_its_order_and_exports(): void
    {
        $cash = $this->account('Main Cash');
        $this->txn($cash, 'credit', 100000, '2026-01-10', 100000, 'Opening float');
        $this->txn($cash, 'debit', 2500.5, '2026-01-12', 97499.5, 'Chalk');
        $this->txn($cash, 'credit', 1000, '2026-01-15', 98499.5, 'Canteen');

        $page = $this->get(route('admin.payment-account.account-book', $cash))->assertOk()
            ->assertSee('data-list-region="account-book"', false)
            ->assertDontSee('aria-sort', false);
        $this->assertSame('Canteen', $page->viewData('transactions')->first()->title);

        // A sort asked for in the address bar is ignored: the balance follows the order.
        $sorted = $this->get(route('admin.payment-account.account-book', [$cash, 'sort' => 'description', 'dir' => 'asc']));
        $this->assertSame('Canteen', $sorted->viewData('transactions')->first()->title);

        $lines = $this->csv($this->get(route('admin.payment-account.account-book', [$cash, 'export' => 'csv'])));
        $this->assertCount(1 + 3, $lines);
        $this->assertSame('15/01/2026,Canteen,Manual,,,1000,98499.5', $lines[1]);
        $this->assertSame('12/01/2026,Chalk,Manual,,2500.5,,97499.5', $lines[2]);
        $this->assertSame('10/01/2026,"Opening float",Manual,,,100000,100000', $lines[3]);
    }

    // ── Fund transfers ───────────────────────────────────────────────────

    public function test_transfer_list(): void
    {
        $cash = $this->account('Cash');
        $bank = $this->account('Bank');
        PaymentAccountTransfer::create(['branch_id' => $this->branch->id, 'from_account_id' => $cash->id, 'to_account_id' => $bank->id, 'amount' => 50000, 'transfer_date' => '2026-02-01', 'note' => 'Banking']);
        PaymentAccountTransfer::create(['branch_id' => $this->branch->id, 'from_account_id' => $bank->id, 'to_account_id' => $cash->id, 'amount' => 7500.25, 'transfer_date' => '2026-02-03', 'note' => 'Float']);

        $page = $this->get(route('admin.payment-account-transfer.index'))->assertOk()
            ->assertSee('data-list-region="transfers"', false);
        $this->assertSame('Float', $page->viewData('transfers')->first()->note, 'newest first');

        $byFrom = $this->get(route('admin.payment-account-transfer.index', ['sort' => 'from', 'dir' => 'desc']));
        $this->assertSame('Banking', $byFrom->viewData('transfers')->first()->note, 'Cash after Bank, reversed');

        $lines = $this->csv($this->get(route('admin.payment-account-transfer.index', ['sort' => 'amount', 'export' => 'csv'])));
        $this->assertCount(1 + 2, $lines);
        $this->assertStringStartsWith('03/02/2026,Bank,Cash,7500.25,Float', $lines[1]);
        $this->assertStringStartsWith('01/02/2026,Cash,Bank,50000,Banking', $lines[2]);
    }

    // ── Cash flow report ─────────────────────────────────────────────────

    public function test_cashflow_report(): void
    {
        $cash = $this->account('Cash');
        $bank = $this->account('Bank');
        $this->txn($cash, 'credit', 30000, '2026-03-01', 30000, 'Fees');
        $this->txn($bank, 'debit', 1200.4, '2026-03-02', -1200.4, 'Charges');

        $page = $this->get(route('admin.payment-account-report.cashflow'))->assertOk()
            ->assertSee('data-list-region="cashflow"', false);
        $this->assertSame('Charges', $page->viewData('transactions')->first()->title);
        $this->assertEquals(30000, $page->viewData('summary')['total_credit']);

        $byAccount = $this->get(route('admin.payment-account-report.cashflow', ['sort' => 'account']));
        $this->assertSame('Charges', $byAccount->viewData('transactions')->first()->title, 'Bank before Cash');
        $byAccount = $this->get(route('admin.payment-account-report.cashflow', ['sort' => 'account', 'dir' => 'desc']));
        $this->assertSame('Fees', $byAccount->viewData('transactions')->first()->title);

        $lines = $this->csv($this->get(route('admin.payment-account-report.cashflow', ['transaction_type' => 'debit', 'export' => 'csv'])));
        $this->assertCount(1 + 1, $lines);
        $this->assertSame('02/03/2026,Bank,Charges,,1200.4,,-1200.4', $lines[1]);
    }

    // ── Journal entries ──────────────────────────────────────────────────

    public function test_journal_entry_list(): void
    {
        foreach ([['JE-1', '2026-04-01', 1000, true], ['JE-2', '2026-04-05', 250000.5, false]] as [$number, $date, $total, $posted]) {
            JournalEntry::create([
                'branch_id' => $this->branch->id, 'entry_number' => $number, 'entry_date' => $date,
                'journal_type' => 'general', 'description' => 'Entry '.$number,
                'total_debit' => $total, 'total_credit' => $total, 'is_posted' => $posted,
            ]);
        }

        $page = $this->get(route('admin.journal-entries.index'))->assertOk()
            ->assertSee('data-list-region="journal-entries"', false);
        $this->assertSame('JE-2', $page->viewData('entries')->first()->entry_number);

        $byDebit = $this->get(route('admin.journal-entries.index', ['sort' => 'total_debit']));
        $this->assertSame('JE-1', $byDebit->viewData('entries')->first()->entry_number);

        $lines = $this->csv($this->get(route('admin.journal-entries.index', ['status' => 'draft', 'export' => 'csv'])));
        $this->assertCount(1 + 1, $lines);
        $this->assertSame('JE-2,05/04/2026,'.__('General').',"Entry JE-2",250000.5,250000.5,'.__('Draft'), $lines[1]);
    }

    // ── Recurring entries ────────────────────────────────────────────────

    public function test_recurring_entry_list(): void
    {
        $rent = $this->chartAccount('622', 6);
        $bank = $this->chartAccount('521', 5);
        foreach ([['Rent', 50000, '2026-12-01'], ['Security', 120000.5, '2026-12-15']] as [$title, $amount, $next]) {
            $template = RecurringJournalEntry::create([
                'branch_id' => $this->branch->id, 'title' => $title, 'frequency' => 'monthly',
                'start_date' => '2026-01-01', 'next_run_date' => $next, 'is_active' => true, 'auto_post' => false,
            ]);
            $template->lines()->create(['account_id' => $rent->id, 'line_number' => 1, 'debit' => $amount, 'credit' => 0]);
            $template->lines()->create(['account_id' => $bank->id, 'line_number' => 2, 'debit' => 0, 'credit' => $amount]);
        }

        $page = $this->get(route('admin.recurring-entries.index'))->assertOk()
            ->assertSee('data-list-region="recurring-entries"', false);
        $this->assertSame('Rent', $page->viewData('templates')->first()->title, 'soonest next run first');

        $byAmount = $this->get(route('admin.recurring-entries.index', ['sort' => 'amount', 'dir' => 'desc']));
        $this->assertSame('Security', $byAmount->viewData('templates')->first()->title);

        $lines = $this->csv($this->get(route('admin.recurring-entries.index', ['sort' => 'amount', 'dir' => 'desc', 'export' => 'csv'])));
        $this->assertCount(1 + 2, $lines);
        $this->assertStringStartsWith('Security,'.__('Monthly').',120000.5,15/12/2026,0,', $lines[1]);
        $this->assertStringStartsWith('Rent,'.__('Monthly').',50000,01/12/2026,0,', $lines[2]);
    }

    // ── Fixed assets ─────────────────────────────────────────────────────

    public function test_fixed_asset_list(): void
    {
        $furniture = FixedAssetCategory::create(['branch_id' => $this->branch->id, 'name' => 'Furniture', 'useful_life_years' => 5, 'method' => 'straight_line', 'is_active' => true]);
        foreach ([['FA-1', 'Tables', '2026-01-01', 120000], ['FA-2', 'Chairs', '2026-03-01', 45000.5]] as [$code, $name, $date, $cost]) {
            FixedAsset::create([
                'branch_id' => $this->branch->id, 'fixed_asset_category_id' => $furniture->id,
                'code' => $code, 'name' => $name, 'acquisition_date' => $date, 'cost' => $cost,
                'salvage_value' => 0, 'useful_life_years' => 5, 'method' => 'straight_line', 'status' => 'active',
            ]);
        }

        $page = $this->get(route('admin.fixed-assets.index'))->assertOk()
            ->assertSee('data-list-region="fixed-assets"', false);
        $this->assertSame('Chairs', $page->viewData('assets')->first()->name, 'newest acquisition first');

        $byCost = $this->get(route('admin.fixed-assets.index', ['sort' => 'cost', 'dir' => 'desc']));
        $this->assertSame('Tables', $byCost->viewData('assets')->first()->name);

        $lines = $this->csv($this->get(route('admin.fixed-assets.index', ['export' => 'csv'])));
        $this->assertCount(1 + 2, $lines);
        $this->assertSame('Chairs,FA-2,Furniture,01/03/2026,45000.5,0,45000.5,'.__('Active'), $lines[1]);
        $this->assertSame('Tables,FA-1,Furniture,01/01/2026,120000,0,120000,'.__('Active'), $lines[2]);
    }

    // ── Budgets ──────────────────────────────────────────────────────────

    public function test_budget_list(): void
    {
        $science = Department::create(['branch_id' => $this->branch->id, 'name' => 'Science']);
        $low = Budget::create([
            'branch_id' => $this->branch->id, 'title' => 'Lab budget', 'type' => 'departmental', 'department_id' => $science->id,
            'fiscal_year' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
            'total_amount' => 100000, 'spent_amount' => 10000, 'status' => 'active', 'created_by' => $this->admin->id,
        ]);
        $high = Budget::create([
            'branch_id' => $this->branch->id, 'title' => 'School budget', 'type' => 'annual',
            'fiscal_year' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
            'total_amount' => 500000.5, 'spent_amount' => 400000, 'status' => 'pending_approval', 'created_by' => $this->admin->id,
        ]);

        $page = $this->get(route('admin.budget.index'))->assertOk()
            ->assertSee('data-list-region="budgets"', false);
        $this->assertSame($high->id, $page->viewData('budgets')->first()->id, 'newest first');

        $byUse = $this->get(route('admin.budget.index', ['sort' => 'utilization']));
        $this->assertSame($low->id, $byUse->viewData('budgets')->first()->id, '10% before 80%');

        $lines = $this->csv($this->get(route('admin.budget.index', ['type' => 'annual', 'export' => 'csv'])));
        $this->assertCount(1 + 1, $lines);
        $this->assertStringContainsString(',"School budget",'.__('Annual').',2026,,500000.5,80,"'.__('Pending approval').'"', $lines[1]);
    }

    // ── Tax settings ─────────────────────────────────────────────────────

    public function test_tax_setting_list(): void
    {
        $irpp = TaxGroup::create(['branch_id' => $this->branch->id, 'title' => 'IRPP', 'code' => 'IRPP', 'is_progressive' => true, 'display_order' => 1, 'status' => true]);
        TaxSetting::create(['branch_id' => $this->branch->id, 'tax_title' => 'Band 2', 'tax_group_id' => $irpp->id, 'bracket_order' => 2, 'min_amount' => 2000000, 'max_amount' => 3000000, 'tax_type' => 1, 'percentage' => 15, 'paid_by' => 'employee']);
        TaxSetting::create(['branch_id' => $this->branch->id, 'tax_title' => 'Band 1', 'tax_group_id' => $irpp->id, 'bracket_order' => 1, 'min_amount' => 0, 'max_amount' => 2000000.5, 'tax_type' => 1, 'percentage' => 10, 'paid_by' => 'employee']);
        TaxSetting::create(['branch_id' => $this->branch->id, 'tax_title' => 'Council tax', 'bracket_order' => 3, 'min_amount' => 0, 'max_amount' => 0, 'tax_type' => 2, 'fixed_amount' => 500, 'paid_by' => 'employer']);

        $page = $this->get(route('admin.tax-settings.index'))->assertOk()
            ->assertSee('data-list-region="tax-settings"', false);
        $this->assertSame(['Band 1', 'Band 2', 'Council tax'], $page->viewData('settings')->getCollection()->pluck('tax_title')->all(), 'bracket order');

        $byMax = $this->get(route('admin.tax-settings.index', ['sort' => 'max', 'dir' => 'desc']));
        $this->assertSame('Band 2', $byMax->viewData('settings')->first()->tax_title);

        $lines = $this->csv($this->get(route('admin.tax-settings.index', ['export' => 'csv'])));
        $this->assertCount(1 + 3, $lines);
        $this->assertSame('"Band 1",IRPP,1,0,2000000.5,10%,'.__('Employee'), $lines[1]);
        $this->assertSame('"Council tax",'.__('Standalone').',3,0,0,500,'.__('Employer'), $lines[3]);
    }
}
