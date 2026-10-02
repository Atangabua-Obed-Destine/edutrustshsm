<?php

namespace App\Services;

use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\PaymentAccount;
use App\Models\PaymentAccountTransaction;
use App\Models\TaxRemittance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * What the school is holding on the tax office's and the CNPS's behalf, and
 * the record of paying it over.
 *
 * Paying a payslip withholds tax and posts it to a liability account, and there
 * the cycle stopped: the account accumulated month after month with nothing to
 * clear it. This is the reference system's TaxRemittanceService behaviour on
 * this system's payroll:
 *
 * - The unit is the salary MONTH, because that is what a declaration is made
 *   for and what an inspection asks about.
 * - What is OWED comes from the ledger — the payroll entries' credits to each
 *   class 4 liability account — not from the payroll rows. The ledger is what
 *   has to be cleared; paying a figure worked out elsewhere could clear the
 *   month on screen and leave the account permanently non-zero.
 * - The authority is the liability account payroll credited. Today payroll
 *   posts both employee and employer tax to one account; if it is ever split
 *   into State (443) and CNPS (431), this separates them with no change.
 * - A remittance is DR liability → CR cash or bank. Neither side is an expense:
 *   the cost was recognised when the salary was paid.
 * - Voiding posts a reversing entry and keeps the row. Nothing is deleted.
 *
 * One addition over the reference: a remittance can name the payment account
 * the money left, so treasury moves with the ledger the same way payroll does.
 */
class TaxRemittanceService
{
    public function __construct(private PaymentAccountService $accounts)
    {
    }

    /**
     * Every authority-and-month with tax withheld or already paid.
     *
     * @return array<int, array<string, mixed>>
     */
    public function outstanding(): array
    {
        // Payroll entries and their reversals share a reference type, so an
        // un-paid payroll nets to nothing rather than leaving a month owed.
        $withheld = DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->join('payrolls as p', 'p.id', '=', 'e.reference_id')
            ->join('chart_of_accounts as a', 'a.id', '=', 'l.account_id')
            ->where('e.reference_type', 'payroll')
            ->where('e.is_posted', true)
            ->where('a.account_code', 'like', '4%')
            ->groupBy('l.account_id', 'p.salary_month')
            ->selectRaw('l.account_id as account_id, p.salary_month as month,
                         COALESCE(SUM(l.credit), 0) - COALESCE(SUM(l.debit), 0) as due')
            ->get()
            ->keyBy(fn ($r) => $r->account_id.'|'.$r->month);

        $paid = TaxRemittance::live()
            ->groupBy('liability_account_id', 'salary_month')
            ->selectRaw('liability_account_id as account_id, salary_month as month, SUM(amount) as paid')
            ->get()
            ->keyBy(fn ($r) => $r->account_id.'|'.$r->month);

        // What each month is made of, for the declaration form. Payroll rows
        // carry the split between employee and employer tax.
        $composition = DB::table('payrolls')
            ->where('status', 1)
            ->groupBy('salary_month')
            ->selectRaw('salary_month as month, SUM(tax) as employee, SUM(employer_tax) as employer, COUNT(*) as staff_count')
            ->get()
            ->keyBy('month');

        $keys = $withheld->keys()->merge($paid->keys())->unique();
        $accounts = ChartOfAccount::withTrashed()
            ->whereIn('id', $keys->map(fn ($k) => (int) explode('|', $k)[0])->unique())
            ->get()
            ->keyBy('id');

        $rows = [];

        foreach ($keys as $key) {
            [$accountId, $month] = explode('|', $key, 2);

            $due = round((float) ($withheld[$key]->due ?? 0), 2);
            $settled = round((float) ($paid[$key]->paid ?? 0), 2);

            if (abs($due) < 0.01 && abs($settled) < 0.01) {
                continue;
            }

            $account = $accounts->get((int) $accountId);
            $parts = $composition->get($month);

            $rows[] = [
                'account_id' => (int) $accountId,
                'account_code' => $account?->account_code ?? '?',
                'account_name' => $account?->account_name ?? __('Unknown account'),
                'month' => $month,
                'month_label' => $this->monthLabel($month),
                'employee' => round((float) ($parts->employee ?? 0), 2),
                'employer' => round((float) ($parts->employer ?? 0), 2),
                'staff_count' => (int) ($parts->staff_count ?? 0),
                'due' => $due,
                'paid' => $settled,
                'outstanding' => round($due - $settled, 2),
                'status' => $this->statusFor($due, $settled),
            ];
        }

        // Oldest first: that is the one at risk of a penalty.
        usort($rows, fn ($a, $b) => [$a['month'], $a['account_code']] <=> [$b['month'], $b['account_code']]);

        return $rows;
    }

    /**
     * Does the per-month picture agree with what each liability account holds?
     *
     * A difference means the account was touched by something other than
     * payroll and remittances — typically a hand-typed journal entry — and the
     * screen has to say so rather than show a confident figure.
     *
     * @return array<int, array<string, mixed>>
     */
    public function reconciliation(): array
    {
        $expected = [];

        foreach ($this->outstanding() as $row) {
            $expected[$row['account_id']] = ($expected[$row['account_id']] ?? 0) + $row['outstanding'];
        }

        $rows = [];

        foreach ($expected as $accountId => $amount) {
            $account = ChartOfAccount::withTrashed()->find($accountId);

            if (! $account) {
                continue;
            }

            $ledger = $this->ledgerBalance($accountId);
            $difference = round($ledger - $amount, 2);

            $rows[] = [
                'account_id' => $accountId,
                'account_code' => $account->account_code,
                'account_name' => $account->account_name,
                'per_month' => round($amount, 2),
                'ledger' => $ledger,
                'difference' => $difference,
                'agrees' => abs($difference) < 0.51,
            ];
        }

        return $rows;
    }

    /** What a liability account carries, from posted entries only. */
    public function ledgerBalance(int $accountId): float
    {
        $row = DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('l.account_id', $accountId)
            ->where('e.is_posted', true)
            ->selectRaw('COALESCE(SUM(l.credit), 0) - COALESCE(SUM(l.debit), 0) as balance')
            ->first();

        return round((float) $row->balance, 2);
    }

    /**
     * Record a payment to an authority and post it.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws RuntimeException when the payment cannot stand as recorded
     */
    public function record(array $input): TaxRemittance
    {
        $month = Carbon::parse($input['salary_month'].'-01')->format('Y-m');
        $amount = round((float) $input['amount'], 2);

        if ($amount <= 0) {
            throw new RuntimeException(__('A remittance has to be for more than zero.'));
        }

        $liability = ChartOfAccount::find($input['liability_account_id']);
        $source = ChartOfAccount::find($input['source_account_id']);

        if (! $liability || ! $source) {
            throw new RuntimeException(__('That account no longer exists.'));
        }

        if (! str_starts_with((string) $liability->account_code, '4')) {
            throw new RuntimeException(__('Tax is remitted against a liability account (class 4).'));
        }

        // Paying from an expense account would post the cost a second time.
        if (! str_starts_with((string) $source->account_code, '5') || str_starts_with((string) $source->account_code, '59')) {
            throw new RuntimeException(__('Tax has to be paid from a cash or bank account (class 5).'));
        }

        $already = (float) TaxRemittance::live()
            ->where('liability_account_id', $liability->id)
            ->where('salary_month', $month)
            ->sum('amount');

        if ($already > 0 && empty($input['allow_additional'])) {
            throw new RuntimeException(__('That month has already been paid to this authority. Void the existing payment first, or tick the box to record an additional one.'));
        }

        $due = 0.0;

        foreach ($this->outstanding() as $row) {
            if ($row['account_id'] === (int) $liability->id && $row['month'] === $month) {
                $due = $row['due'];
                break;
            }
        }

        // Paying more than was withheld is sometimes right — a penalty, an
        // adjustment — but it must be deliberate, not a typo that turns the
        // liability negative.
        if ($amount > round($due - $already, 2) + 0.01 && empty($input['allow_overpayment'])) {
            throw new RuntimeException(__('That is more than is outstanding for the month. Tick the overpayment box if it is intended.'));
        }

        return DB::transaction(function () use ($input, $month, $amount, $liability, $source) {
            $remittance = TaxRemittance::create([
                'liability_account_id' => $liability->id,
                'salary_month' => $month,
                'amount' => $amount,
                'payment_date' => $input['payment_date'],
                'source_account_id' => $source->id,
                'payment_account_id' => $input['payment_account_id'] ?? null,
                'reference' => $input['reference'] ?? null,
                'note' => $input['note'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $description = __('Tax remittance').' - '.$liability->account_name.' ('.$this->monthLabel($month).')';

            $entry = $this->postEntry(
                $remittance->payment_date->toDateString(),
                $description,
                $liability->id,
                $source->id,
                $amount,
                'tax_remittance',
                $remittance->id
            );

            $remittance->update(['journal_entry_id' => $entry->id]);

            if ($remittance->payment_account_id) {
                $account = PaymentAccount::whereKey($remittance->payment_account_id)->lockForUpdate()->firstOrFail();

                $this->accounts->debit($account, $amount, [
                    'transaction_date' => $remittance->payment_date->toDateString(),
                    'title' => $description,
                    'description' => $remittance->note,
                    'reference_type' => PaymentAccountTransaction::REF_TAX_REMITTANCE,
                    'reference_id' => $remittance->id,
                    'payment_reference' => $remittance->reference,
                ]);
            }

            return $remittance->fresh();
        });
    }

    /**
     * Undo a remittance with a reversing entry, keeping the row.
     *
     * The money did leave the bank and somebody recorded it; a correction that
     * removed both would be indistinguishable from it never having happened.
     */
    public function void(TaxRemittance $remittance, ?string $reason = null): TaxRemittance
    {
        return DB::transaction(function () use ($remittance, $reason) {
            $remittance = TaxRemittance::whereKey($remittance->getKey())->lockForUpdate()->firstOrFail();

            if ($remittance->isVoided()) {
                throw new RuntimeException(__('That payment has already been voided.'));
            }

            $original = $remittance->journalEntry;
            $reversalId = null;

            if ($original) {
                $line = fn (bool $debitSide) => $original->lines->first(fn ($l) => (float) ($debitSide ? $l->debit : $l->credit) > 0);

                $reversal = $this->postEntry(
                    $this->reversalDate($original),
                    __('Reversal').': '.$original->description,
                    $line(false)->account_id,   // the cash side is debited back
                    $line(true)->account_id,    // the liability is credited back
                    (float) $remittance->amount,
                    'tax_remittance_reversal',
                    $remittance->id,
                    $original->id,
                );

                $original->update(['is_reversed' => true]);
                $reversalId = $reversal->id;
            }

            if ($remittance->payment_account_id) {
                $account = PaymentAccount::whereKey($remittance->payment_account_id)->lockForUpdate()->first();

                if ($account) {
                    $this->accounts->credit($account, $remittance->amount, [
                        'transaction_date' => now()->toDateString(),
                        'title' => __('Void of tax remittance').' - '.$this->monthLabel($remittance->salary_month),
                        'description' => $reason,
                        'reference_type' => PaymentAccountTransaction::REF_TAX_REMITTANCE,
                        'reference_id' => $remittance->id,
                    ]);
                }
            }

            $remittance->update([
                'voided_at' => now(),
                'voided_by' => auth()->id(),
                'void_reason' => $reason,
                'void_journal_entry_id' => $reversalId,
            ]);

            return $remittance->fresh();
        });
    }

    /** Cash and bank ledger accounts a remittance may be paid from. */
    public function sourceAccounts()
    {
        return ChartOfAccount::where('account_code', 'like', '5%')
            ->where('account_code', 'not like', '59%')
            ->where('is_active', true)
            ->orderBy('account_code')
            ->get(['id', 'account_code', 'account_name']);
    }

    public function monthLabel(string $month): string
    {
        return Carbon::parse($month.'-01')->translatedFormat('F Y');
    }

    private function statusFor(float $due, float $paid): string
    {
        if ($paid <= 0) {
            return 'undeclared';
        }

        if (abs($due - $paid) < 0.01) {
            return 'settled';
        }

        return $paid > $due ? 'overpaid' : 'part_paid';
    }

    private function postEntry(
        string $date,
        string $description,
        int $debitAccount,
        int $creditAccount,
        float $amount,
        string $referenceType,
        int $referenceId,
        ?int $reversedEntryId = null,
    ): JournalEntry {
        $fiscalYear = FiscalYear::whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date)->first()
            ?? FiscalYear::active();

        if (! $fiscalYear) {
            throw new RuntimeException(__('There is no fiscal year to post this into.'));
        }

        $entry = JournalEntry::create([
            'entry_number' => JournalEntry::generateEntryNumber(),
            'entry_date' => $date,
            'fiscal_year_id' => $fiscalYear->id,
            'accounting_period_id' => AccountingPeriod::forDate($date)?->id,
            'journal_type' => 'general',
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'description' => $description,
            'is_system_generated' => true,
            'reversed_entry_id' => $reversedEntryId,
            'created_by' => auth()->id(),
        ]);

        $entry->lines()->create(['account_id' => $debitAccount, 'line_number' => 1, 'debit' => $amount, 'credit' => 0, 'description' => $description]);
        $entry->lines()->create(['account_id' => $creditAccount, 'line_number' => 2, 'debit' => 0, 'credit' => $amount, 'description' => $description]);

        // post() refuses a closed period or fiscal year, which rolls the whole
        // remittance back rather than recording a payment the ledger never saw.
        $entry->post();

        return $entry;
    }

    /** Same date as the original unless its period has since closed. */
    private function reversalDate(JournalEntry $original): string
    {
        $date = $original->entry_date->toDateString();
        $period = AccountingPeriod::forDate($date);

        return ($period && $period->is_closed) || $original->fiscalYear?->is_closed
            ? now()->toDateString()
            : $date;
    }
}
