<?php

namespace App\Services\EdutrustPay;

use App\Services\DaybookService;
use Carbon\Carbon;
use EdutrustPay\Contract\Daybook;
use EdutrustPay\Contract\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * One branch's daybook for one month, in the shape the console receives.
 *
 * The lines come from DaybookService, the same book the bursar reads here.
 *
 * DaybookService READS EVERY BRANCH AT ONCE, because it queries the tables directly
 * and the branch scope does not reach those queries. Each branch is a separate school
 * on the console, so its lines are narrowed here. A line that cannot be traced to a
 * branch stops the export rather than being filed under the wrong school.
 *
 * THESE LINES NAME PEOPLE. The body chose to hold them, and the settings screen
 * says so to whoever runs this school.
 */
class DaybookExporter
{
    private const KINDS = [
        'payment' => 'fee',
        'income' => 'income',
        'expense' => 'expense',
        'payroll' => 'payroll',
        'refund' => 'refund',
        'remittance' => 'remittance',
    ];

    public function __construct(private DaybookService $daybook)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function document(int $branchId, string $period, int $sequence = 1): array
    {
        $settings = app(SettingsResolver::class)->forBranch($branchId);

        if ($settings === null) {
            throw new \RuntimeException("Branch $branchId has no EdutrustPay credentials.");
        }

        $month = Carbon::createFromFormat('!Y-m', $period);
        $rows = $this->daybook->rows($month->toDateString(), $month->copy()->endOfMonth()->toDateString());

        $lines = $this->forBranch($rows, $branchId)->map(fn (array $row) => [
            'date' => substr((string) $row['date'], 0, 10),
            'ref' => (string) ($row['ref'] ?: $row['source'].'-'.$row['source_id']),
            'description' => (string) ($row['description'] ?: $row['heading']),
            'party' => $row['party'] ?? null,
            'heading' => (string) $row['heading'],
            'kind' => self::KINDS[$row['source']],
            'direction' => $row['direction'],
            'amount' => Money::of(number_format((float) $row['amount'], 2, '.', '')),
            'method' => $this->method($row['method'] ?? null),
            'source_id' => $row['source'].':'.$row['source_id'],
        ])->values()->all();

        return Daybook::build($settings['institution_ref'], $period, $sequence, gmdate('Y-m-d\TH:i:s\Z'), 'XAF', $lines);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function forBranch(Collection $rows, int $branchId): Collection
    {
        $unknown = $rows->pluck('source')->unique()->diff(array_keys(self::KINDS));

        if ($unknown->isNotEmpty()) {
            throw new \RuntimeException(
                'The daybook has lines from '.$unknown->implode(', ').', which cannot be traced to a branch. '
                .'Add them to DaybookExporter before sending, or they could be filed under the wrong school.'
            );
        }

        $ids = fn (string $source) => $rows->where('source', $source)->pluck('source_id');
        $owned = fn (string $table, string $source) => DB::table($table)
            ->whereIn('id', $ids($source))
            ->where('branch_id', $branchId)
            ->pluck('id')
            ->flip();

        $mine = [
            'payment' => $owned('payments', 'payment'),
            'income' => $owned('incomes', 'income'),
            'expense' => $owned('expenses', 'expense'),
            'payroll' => $owned('payrolls', 'payroll'),
            'remittance' => $owned('tax_remittances', 'remittance'),
            // A refund has no branch of its own: it belongs to the pupil's enrolment.
            'refund' => DB::table('student_credit_refunds as r')
                ->join('student_credits as c', 'c.id', '=', 'r.student_credit_id')
                ->join('student_enrollments as e', 'e.id', '=', 'c.student_enrollment_id')
                ->whereIn('r.id', $ids('refund'))
                ->where('e.branch_id', $branchId)
                ->pluck('r.id')
                ->flip(),
        ];

        return $rows->filter(fn (array $row) => $mine[$row['source']]->has($row['source_id']));
    }

    private function method(?string $method): ?string
    {
        if ($method === null || trim($method) === '') {
            return null;
        }

        $name = strtolower($method);

        return match (true) {
            str_contains($name, 'cash') => 'cash',
            str_contains($name, 'momo'), str_contains($name, 'mobile'), str_contains($name, 'orange'), str_contains($name, 'mtn') => 'mobile_money',
            str_contains($name, 'cheque'), str_contains($name, 'check') => 'cheque',
            str_contains($name, 'bank'), str_contains($name, 'transfer') => 'bank',
            str_contains($name, 'card') => 'card',
            str_contains($name, 'online'), str_contains($name, 'fapshi') => 'online',
            default => 'other',
        };
    }
}
