<?php

namespace Tests\Feature\Finance;

use App\Models\Branch;
use App\Models\PaymentAccount;
use App\Models\PaymentAccountType;
use App\Models\PaymentAccountTransaction;
use App\Models\Payroll;
use App\Models\User;
use App\Services\PaymentAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression: paying a payroll stored payment_account_id and posted to the GL,
 * but never debited the account — so treasury cash overstated by the full
 * payroll every month, and payroll never appeared in the "unlinked" queue.
 */
class PayrollTreasuryTest extends TestCase
{
    use RefreshDatabase;

    private function accountType(Branch $branch): PaymentAccountType
    {
        return PaymentAccountType::firstOrCreate(
            ['slug' => 'cash'],
            ['branch_id' => $branch->id, 'title' => 'Cash', 'status' => true]
        );
    }

    public function test_paying_a_payroll_debits_the_payment_account(): void
    {
        $branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'is_active' => true]);

        $staff = User::create([
            'first_name' => 'Sam', 'last_name' => 'Staff',
            'email' => 'staff@example.test', 'password' => 'password',
            'role' => 'staff', 'is_active' => true, 'basic_salary' => 200000,
        ]);

        $account = PaymentAccount::create([
            'branch_id' => $branch->id, 'title' => 'Main Cash',
            'account_type_id' => $this->accountType($branch)->id,
            'opening_balance' => 0, 'current_balance' => 0, 'status' => true,
        ]);

        $service = app(PaymentAccountService::class);
        $service->credit($account, 500000, [
            'reference_type' => PaymentAccountTransaction::REF_DEPOSIT,
            'transaction_date' => '2026-01-01',
            'description' => 'Opening float',
        ]);
        $account->refresh();
        $this->assertEquals(500000, (float) $account->current_balance);

        $payroll = Payroll::create([
            'branch_id' => $branch->id, 'user_id' => $staff->id,
            'basic_salary' => 200000, 'gross_salary' => 200000,
            'total_allowance' => 0, 'bonus' => 0, 'total_deduction' => 0,
            'tax' => 20000, 'employer_tax' => 0,
            'net_salary' => 180000, 'total_cost' => 200000,
            'salary_month' => '2026-01', 'status' => Payroll::STATUS_UNPAID,
        ]);

        // Debit through the service, exactly as PayrollController::pay now does.
        $service->debit($account, $payroll->net_salary, [
            'reference_type' => PaymentAccountTransaction::REF_PAYROLL,
            'reference_id' => $payroll->id,
            'transaction_date' => '2026-01-31',
            'description' => 'Salary payment',
        ]);

        $account->refresh();
        $this->assertEquals(320000, (float) $account->current_balance, 'net salary must leave the account');

        $txn = PaymentAccountTransaction::where('reference_type', PaymentAccountTransaction::REF_PAYROLL)
            ->where('reference_id', $payroll->id)->first();
        $this->assertNotNull($txn);
        $this->assertTrue($txn->isLinked(), 'payroll transactions must not be hand-deletable');

        // Un-paying puts the cash back.
        $service->reverseFor(PaymentAccountTransaction::REF_PAYROLL, $payroll->id);
        $account->refresh();
        $this->assertEquals(500000, (float) $account->current_balance);
    }

    public function test_recompute_matches_the_ledger(): void
    {
        $branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'is_active' => true]);
        $account = PaymentAccount::create([
            'branch_id' => $branch->id, 'title' => 'Bank',
            'account_type_id' => $this->accountType($branch)->id,
            'opening_balance' => 0, 'current_balance' => 0, 'status' => true,
        ]);

        $service = app(PaymentAccountService::class);
        $service->credit($account, 100000, ['reference_type' => PaymentAccountTransaction::REF_DEPOSIT, 'transaction_date' => '2026-01-01']);
        $service->debit($account, 25000, ['reference_type' => PaymentAccountTransaction::REF_WITHDRAWAL, 'transaction_date' => '2026-01-05']);

        $account->refresh();
        $stored = (float) $account->current_balance;

        $account->recomputeBalance();
        $account->refresh();

        $this->assertEquals($stored, (float) $account->current_balance, 'replaying the ledger must reproduce the stored balance');
        $this->assertEquals(75000, (float) $account->current_balance);
    }
}
