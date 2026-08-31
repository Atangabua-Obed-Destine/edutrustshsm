<?php

namespace App\Console\Commands;

use App\Mail\FeeReminder;
use App\Models\Guardian;
use App\Models\StudentFee;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

/**
 * Chases outstanding school fees by email.
 *
 * The system had complete fee, discount, fine and payment-plan modules and no
 * way at all to tell a parent they owed anything — every reminder was a phone
 * call or a letter sent home with the student.
 *
 * One message per guardian covering every child, not one per fee: a parent with
 * three children should get one letter with three balances.
 */
class SendFeeReminders extends Command
{
    protected $signature = 'fees:remind
        {--days-overdue=0 : Only fees at least this many days past their due date}
        {--min-interval=7 : Days to leave before chasing the same fee again}
        {--dry-run : Report who would be written to without sending anything}';

    protected $description = 'Email guardians about outstanding school fees';

    public function handle(): int
    {
        if (! setting('fees.reminders_enabled', true)) {
            $this->comment('Fee reminders are switched off in settings.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subDays((int) $this->option('days-overdue'))->endOfDay();
        $interval = now()->subDays((int) $this->option('min-interval'));

        $fees = StudentFee::query()
            ->where('balance', '>', 0.005)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', $cutoff)
            // Not chased recently. A daily schedule would otherwise mail the
            // same parent every morning until they paid.
            ->where(fn ($q) => $q->whereNull('last_reminded_at')->orWhere('last_reminded_at', '<=', $interval))
            ->with([
                'enrollment.student.guardian',
                'feeCategory:id,name',
            ])
            ->get();

        if ($fees->isEmpty()) {
            $this->info('Nothing outstanding to chase.');

            return self::SUCCESS;
        }

        $byGuardian = $fees->groupBy(fn (StudentFee $fee) => $fee->enrollment?->student?->guardian_id);

        $sent = 0;
        $noEmail = 0;
        $orphaned = 0;

        foreach ($byGuardian as $guardianId => $guardianFees) {
            if (! $guardianId) {
                // A fee whose student has no guardian record cannot be chased;
                // say so rather than dropping it silently.
                $orphaned += $guardianFees->count();

                continue;
            }

            $guardian = Guardian::find($guardianId);

            if (! $guardian?->primary_email) {
                $noEmail += $guardianFees->count();

                continue;
            }

            $lines = $this->lines($guardianFees);

            $this->line(sprintf(
                '%s%s — %d fee(s), %s outstanding',
                $dryRun ? '[dry-run] ' : '',
                $guardian->primary_email,
                $lines->count(),
                number_format($lines->sum('balance'), 0, '.', ' ')
            ));

            if (! $dryRun) {
                Mail::to($guardian->primary_email)->send(FeeReminder::for($guardian, $lines));

                // Stamped only after the mail is queued, so a failure mid-run
                // leaves those fees to be picked up next time.
                StudentFee::whereIn('id', $guardianFees->pluck('id'))
                    ->update(['last_reminded_at' => now()]);
            }

            $sent++;
        }

        $this->newLine();
        $this->info($dryRun
            ? "{$sent} guardian(s) would be emailed."
            : "{$sent} guardian(s) emailed.");

        if ($noEmail > 0) {
            $this->warn("{$noEmail} fee(s) skipped: the guardian has no email address on file.");
        }

        if ($orphaned > 0) {
            $this->warn("{$orphaned} fee(s) skipped: the student has no guardian record.");
        }

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, StudentFee>  $fees
     * @return Collection<int, object>
     */
    private function lines(Collection $fees): Collection
    {
        return $fees->map(fn (StudentFee $fee) => (object) [
            'student' => $fee->enrollment?->student?->full_name ?? '—',
            'category' => $fee->feeCategory?->name ?? __('School fees'),
            'due_date' => $fee->due_date?->format('d/m/Y'),
            'balance' => (float) $fee->balance,
        ])->values();
    }
}
