<?php

namespace App\Services;

use App\Models\PaymentAccount;
use App\Models\PaymentAccountTransaction;
use RuntimeException;

/**
 * Centralises the atomic balance mechanics for payment accounts: applying a
 * credit/debit transaction (with a balance_after snapshot) and reversing one.
 *
 * Callers are responsible for wrapping multi-step operations in a single
 * DB::transaction(); these helpers only mutate the account + ledger and assume
 * they run inside one.
 */
class PaymentAccountService
{
    /**
     * Money IN: increase the account balance and record a credit transaction.
     *
     * @param array{transaction_date?:mixed,title?:string,description?:string,reference_type?:string,reference_id?:int,payment_method?:string,payment_reference?:string,attach?:string} $meta
     */
    public function credit(PaymentAccount $account, $amount, array $meta = []): PaymentAccountTransaction
    {
        $new = bcadd((string) $account->current_balance, (string) $amount, 2);

        $txn = $this->writeTransaction($account, 'credit', $amount, $new, $meta);
        $account->update(['current_balance' => $new]);

        return $txn;
    }

    /**
     * Money OUT: decrease the account balance and record a debit transaction.
     * Guards against overdrawing unless $guard is false.
     */
    public function debit(PaymentAccount $account, $amount, array $meta = [], bool $guard = true): PaymentAccountTransaction
    {
        if ($guard && bccomp((string) $account->current_balance, (string) $amount, 2) < 0) {
            throw new RuntimeException(
                'Insufficient balance in "' . $account->title . '". Available: ' . $account->current_balance
            );
        }

        $new = bcsub((string) $account->current_balance, (string) $amount, 2);

        $txn = $this->writeTransaction($account, 'debit', $amount, $new, $meta);
        $account->update(['current_balance' => $new]);

        return $txn;
    }

    /**
     * Undo a transaction's effect on its account balance and delete the row.
     * (credit reverses by subtracting; debit reverses by adding.)
     */
    public function reverseTransaction(PaymentAccountTransaction $txn): void
    {
        $account = $txn->paymentAccount;

        if ($account) {
            $new = $txn->transaction_type === 'credit'
                ? bcsub((string) $account->current_balance, (string) $txn->amount, 2)
                : bcadd((string) $account->current_balance, (string) $txn->amount, 2);

            $account->update(['current_balance' => $new]);
        }

        $txn->delete();
    }

    /**
     * Reverse any existing transaction(s) that point at a given source record,
     * then return how many were reversed. Used by income/expense update/delete
     * to keep the cash ledger in sync (no drift).
     */
    public function reverseFor(string $referenceType, int $referenceId): int
    {
        $txns = PaymentAccountTransaction::where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->get();

        foreach ($txns as $txn) {
            $this->reverseTransaction($txn);
        }

        return $txns->count();
    }

    private function writeTransaction(PaymentAccount $account, string $type, $amount, string $balanceAfter, array $meta): PaymentAccountTransaction
    {
        return PaymentAccountTransaction::create([
            'payment_account_id' => $account->id,
            'transaction_type'   => $type,
            'amount'             => $amount,
            'transaction_date'   => $meta['transaction_date'] ?? now()->toDateString(),
            'title'              => $meta['title'] ?? null,
            'description'        => $meta['description'] ?? null,
            'reference_type'     => $meta['reference_type'] ?? null,
            'reference_id'       => $meta['reference_id'] ?? null,
            'payment_method'     => $meta['payment_method'] ?? null,
            'payment_reference'  => $meta['payment_reference'] ?? null,
            'balance_after'      => $balanceAfter,
            'attach'             => $meta['attach'] ?? null,
            'created_by'         => auth()->id(),
        ]);
    }
}
