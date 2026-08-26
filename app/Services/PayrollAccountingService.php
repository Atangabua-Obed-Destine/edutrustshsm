<?php

namespace App\Services;

use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\Payroll;
use App\Models\TransactionMapping;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The single HR→GL bridge. Paying a payslip posts a balanced multi-line OHADA
 * journal entry; un-paying reverses it.
 *
 *   DR Salary expense (661)            gross_salary
 *   DR Employer charges (641)          employer_tax        (if > 0)
 *      CR Taxes payable (441)          employee_tax        (if > 0)
 *      CR Social charges payable (441) employer_tax        (if > 0)
 *      CR Cash/Bank (521 / 571)        net_salary
 *
 * Balanced because debits (gross + employer_tax) == credits
 * (employee_tax + employer_tax + net), and net = gross − employee_tax.
 */
class PayrollAccountingService
{
    public function createPayrollJournalEntry(Payroll $payroll): ?JournalEntry
    {
        // Idempotent: skip if already actively mapped.
        $existing = TransactionMapping::where('transaction_type', 'payroll')
            ->where('transaction_id', $payroll->id)->first();
        if ($existing && $existing->status === 'active') {
            return $existing->journalEntry;
        }

        $salaryExpense = $this->account(['661', '66', '6']);
        $employerCharges = $this->account(['641', '64', '661']);
        $taxPayable = $this->account(['441', '44', '47']);
        $cash = $this->cashAccount($payroll);

        if (! $salaryExpense || ! $taxPayable || ! $cash) {
            throw new RuntimeException('Payroll accounts are not configured in the Chart of Accounts (need salary expense, tax payable, cash/bank).');
        }

        $date = ($payroll->pay_date ?? now())->toDateString();

        return DB::transaction(function () use ($payroll, $salaryExpense, $employerCharges, $taxPayable, $cash, $date, $existing) {
            $entry = $this->buildEntry($payroll, $date, [
                ['account' => $salaryExpense, 'debit' => $payroll->gross_salary, 'credit' => 0, 'desc' => 'Gross salary'],
                ['account' => $employerCharges, 'debit' => $payroll->employer_tax, 'credit' => 0, 'desc' => 'Employer charges'],
                ['account' => $taxPayable, 'debit' => 0, 'credit' => $payroll->tax, 'desc' => 'Employee tax payable'],
                ['account' => $taxPayable, 'debit' => 0, 'credit' => $payroll->employer_tax, 'desc' => 'Employer tax payable'],
                ['account' => $cash, 'debit' => 0, 'credit' => $payroll->net_salary, 'desc' => 'Net salary paid'],
            ]);

            TransactionMapping::updateOrCreate(
                ['transaction_type' => 'payroll', 'transaction_id' => $payroll->id],
                [
                    'debit_account_id' => $salaryExpense->id,
                    'credit_account_id' => $cash->id,
                    'amount' => $payroll->total_cost,
                    'journal_entry_id' => $entry->id,
                    'status' => 'active',
                ]
            );

            return $entry;
        });
    }

    public function reversePayrollJournalEntry(Payroll $payroll): void
    {
        $mapping = TransactionMapping::where('transaction_type', 'payroll')
            ->where('transaction_id', $payroll->id)->where('status', 'active')->first();
        if (! $mapping || ! $mapping->journalEntry) {
            return;
        }

        $original = $mapping->journalEntry;

        DB::transaction(function () use ($payroll, $original, $mapping) {
            // Rebuild with debit/credit swapped to neutralise the original.
            $lines = $original->lines->map(fn ($l) => [
                'account' => $l->account,
                'debit' => $l->credit,
                'credit' => $l->debit,
                'desc' => 'Reversal: ' . $l->description,
            ])->all();

            $this->buildEntry($payroll, now()->toDateString(), $lines, reversedEntryId: $original->id, prefix: 'Reversal of ');
            $original->update(['is_reversed' => true]);
            $mapping->update(['status' => 'reversed']);
        });
    }

    private function buildEntry(Payroll $payroll, string $date, array $lines, ?int $reversedEntryId = null, string $prefix = ''): JournalEntry
    {
        $fy = FiscalYear::active();
        $period = AccountingPeriod::forDate($date);

        $entry = JournalEntry::create([
            'entry_number' => JournalEntry::generateEntryNumber(),
            'entry_date' => $date,
            'fiscal_year_id' => $fy?->id,
            'accounting_period_id' => $period?->id,
            'journal_type' => 'general',
            'reference_type' => 'payroll',
            'reference_id' => $payroll->id,
            'description' => $prefix . 'Payroll ' . $payroll->salary_month . ' - ' . ($payroll->user?->full_name ?? ''),
            'is_system_generated' => true,
            'reversed_entry_id' => $reversedEntryId,
        ]);

        $n = 1;
        foreach ($lines as $line) {
            if (! $line['account'] || ((float) $line['debit'] == 0 && (float) $line['credit'] == 0)) {
                continue; // skip zero / missing-account lines
            }
            $entry->lines()->create([
                'account_id' => $line['account']->id,
                'line_number' => $n++,
                'debit' => $line['debit'],
                'credit' => $line['credit'],
                'description' => $line['desc'] ?? null,
            ]);
        }

        $entry->post();

        return $entry;
    }

    /** First postable account matching one of the given code prefixes. */
    private function account(array $codes): ?ChartOfAccount
    {
        foreach ($codes as $code) {
            $a = ChartOfAccount::postable()->where('account_code', $code)->first()
                ?? ChartOfAccount::postable()->where('account_code', 'like', $code . '%')->orderBy('account_code')->first();
            if ($a) {
                return $a;
            }
        }
        return null;
    }

    private function cashAccount(Payroll $payroll): ?ChartOfAccount
    {
        // Prefer bank (521) for bank transfers, cash box (571) otherwise.
        $method = strtolower((string) $payroll->payment_method);
        if (str_contains($method, 'cash')) {
            return $this->account(['571', '57', '521', '52']);
        }
        return $this->account(['521', '52', '571', '57']);
    }
}
