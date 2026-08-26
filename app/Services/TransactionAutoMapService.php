<?php

namespace App\Services;

use App\Models\AccountingPeriod;
use App\Models\DefaultAccountMapping;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\TransactionMapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Turns an operational fact (income/expense/fee payment) into a balanced,
 * posted 2-line journal entry, using the configured DefaultAccountMapping for
 * its category. Idempotent, drift-free (reverse/remap on edit/delete).
 */
class TransactionAutoMapService
{
    /**
     * Create + post the journal entry for a fact, and record the TransactionMapping.
     * Returns true if posted, false if no mapping configured (fact left unmapped).
     *
     * @param  array{amount:float|string,date:string,description?:string}  $data
     */
    public function autoMap(string $type, int $id, ?int $categoryId, array $data): bool
    {
        // Idempotent: skip if an active mapping already exists for this fact.
        $existing = TransactionMapping::where('transaction_type', $type)
            ->where('transaction_id', $id)->first();
        if ($existing && $existing->status === 'active') {
            return true;
        }

        $rule = $this->findRule($type, $categoryId);
        if (! $rule || ! $rule->debit_account_id || ! $rule->credit_account_id) {
            Log::warning('Auto-map: no account mapping configured', compact('type', 'id', 'categoryId'));
            return false;
        }

        DB::transaction(function () use ($type, $id, $data, $rule, $existing) {
            $entry = $this->buildEntry(
                $rule->debit_account_id,
                $rule->credit_account_id,
                $data['amount'],
                $data['date'],
                $data['description'] ?? ucfirst(str_replace('_', ' ', $type)),
                $type,
                $id
            );

            TransactionMapping::updateOrCreate(
                ['transaction_type' => $type, 'transaction_id' => $id],
                [
                    'debit_account_id' => $rule->debit_account_id,
                    'credit_account_id' => $rule->credit_account_id,
                    'amount' => $data['amount'],
                    'journal_entry_id' => $entry->id,
                    'status' => 'active',
                ]
            );
        });

        return true;
    }

    /** Post a reversal (debit/credit swapped) and mark the mapping reversed. */
    public function reverse(string $type, int $id): void
    {
        $mapping = TransactionMapping::where('transaction_type', $type)
            ->where('transaction_id', $id)->where('status', 'active')->first();
        if (! $mapping) {
            return;
        }

        DB::transaction(function () use ($mapping, $type, $id) {
            // Swap debit/credit to neutralise the original entry.
            $original = $mapping->journalEntry;
            $this->buildEntry(
                $mapping->credit_account_id,
                $mapping->debit_account_id,
                $mapping->amount,
                now()->toDateString(),
                'Reversal of ' . ($original?->entry_number ?? "{$type} #{$id}"),
                $type,
                $id,
                reversedEntryId: $original?->id
            );

            if ($original) {
                $original->update(['is_reversed' => true]);
            }
            $mapping->update(['status' => 'reversed']);
        });
    }

    /** reverse() then autoMap() — used when a posted amount/category changes. */
    public function remap(string $type, int $id, ?int $categoryId, array $data): void
    {
        $this->reverse($type, $id);
        $this->autoMap($type, $id, $categoryId, $data);
    }

    private function findRule(string $type, ?int $categoryId): ?DefaultAccountMapping
    {
        return DefaultAccountMapping::where('mapping_type', $type)
            ->where('status', 'active')
            ->where(function ($q) use ($categoryId) {
                $q->where('category_id', $categoryId)->orWhereNull('category_id');
            })
            ->orderByRaw('category_id IS NULL ASC') // category-specific rule wins over the catch-all
            ->first();
    }

    private function buildEntry($debitAccountId, $creditAccountId, $amount, string $date, string $description, string $type, int $id, ?int $reversedEntryId = null): JournalEntry
    {
        $fy = FiscalYear::active();
        $period = AccountingPeriod::forDate($date);

        $entry = JournalEntry::create([
            'entry_number' => JournalEntry::generateEntryNumber(),
            'entry_date' => $date,
            'fiscal_year_id' => $fy?->id,
            'accounting_period_id' => $period?->id,
            'journal_type' => 'general',
            'reference_type' => $type,
            'reference_id' => $id,
            'description' => $description,
            'is_system_generated' => true,
            'reversed_entry_id' => $reversedEntryId,
        ]);

        $entry->lines()->create([
            'account_id' => $debitAccountId,
            'line_number' => 1,
            'debit' => $amount,
            'credit' => 0,
            'description' => $description,
        ]);
        $entry->lines()->create([
            'account_id' => $creditAccountId,
            'line_number' => 2,
            'debit' => 0,
            'credit' => $amount,
            'description' => $description,
        ]);

        $entry->post();

        return $entry;
    }
}
