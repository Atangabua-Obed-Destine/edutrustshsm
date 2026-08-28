<?php

namespace App\Services;

use App\Models\AccountingPeriod;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\RecurringJournalEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Turns recurring templates into real journal entries.
 *
 * Generation is idempotent per run date: a template that has already produced
 * an entry for a given date will not produce a second one, so re-running the
 * scheduler after a failure is safe.
 */
class RecurringEntryService
{
    /**
     * Generate every template that is due.
     *
     * @return array{generated: int, skipped: int, errors: array<int, string>}
     */
    public function runDue(?string $asOf = null): array
    {
        $asOf = $asOf ?: now()->toDateString();

        $generated = 0;
        $skipped = 0;
        $errors = [];

        foreach (RecurringJournalEntry::due($asOf)->with('lines')->get() as $template) {
            try {
                $entry = $this->generate($template, $asOf);
                $entry ? $generated++ : $skipped++;
            } catch (RuntimeException $e) {
                $errors[] = $template->title.': '.$e->getMessage();
            }
        }

        return ['generated' => $generated, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * Generate one entry from a template and advance its schedule.
     *
     * Returns null when an entry for this run already exists.
     */
    public function generate(RecurringJournalEntry $template, ?string $asOf = null): ?JournalEntry
    {
        $runDate = $template->next_run_date->copy();
        $asOf = $asOf ? Carbon::parse($asOf) : now();

        if ($runDate->gt($asOf)) {
            return null;
        }

        if ($template->lines->isEmpty()) {
            throw new RuntimeException(__('This template has no lines.'));
        }

        if (! $template->isBalanced()) {
            throw new RuntimeException(__('This template does not balance: debits must equal credits.'));
        }

        // Idempotence: one entry per template per run date.
        $already = JournalEntry::where('reference_type', 'recurring_entry')
            ->where('reference_id', $template->id)
            ->whereDate('entry_date', $runDate)
            ->exists();

        if ($already) {
            $this->advance($template, $runDate);

            return null;
        }

        return DB::transaction(function () use ($template, $runDate) {
            $entry = JournalEntry::create([
                'branch_id' => $template->branch_id,
                'entry_number' => JournalEntry::generateEntryNumber(),
                'entry_date' => $runDate->toDateString(),
                'fiscal_year_id' => FiscalYear::active()?->id,
                'accounting_period_id' => AccountingPeriod::forDate($runDate->toDateString())?->id,
                'journal_type' => 'general',
                'reference_type' => 'recurring_entry',
                'reference_id' => $template->id,
                'description' => $template->description ?: $template->title,
                'is_system_generated' => true,
            ]);

            foreach ($template->lines as $line) {
                $entry->lines()->create([
                    'account_id' => $line->account_id,
                    'line_number' => $line->line_number,
                    'debit' => $line->debit,
                    'credit' => $line->credit,
                    'description' => $line->description ?: $template->title,
                ]);
            }

            // Left as a draft unless the template says otherwise: a standing
            // charge that posts itself into a closed period would fail loudly
            // every month, and some schools want to review before posting.
            if ($template->auto_post) {
                $entry->post();
            }

            $this->advance($template, $runDate);

            return $entry->fresh('lines');
        });
    }

    /** Move the template on to its next occurrence. */
    private function advance(RecurringJournalEntry $template, Carbon $runDate): void
    {
        $template->update([
            'last_run_date' => $runDate->toDateString(),
            'next_run_date' => $template->advanceFrom($runDate)->toDateString(),
            'runs_generated' => $template->runs_generated + 1,
        ]);

        // A template past its end date stops rather than piling up work.
        if ($template->fresh()->hasFinished()) {
            $template->update(['is_active' => false]);
        }
    }
}
