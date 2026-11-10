<?php

namespace App\Exports;

use App\Models\Budget;
use App\Models\SchoolSetting;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * The cash book as a workbook, shaped like the bursar's own file: the month of
 * movements, the year month by month, and the month against the Income &
 * Expenditure sheet. Follows the reference system's daybook workbook; the year
 * tab is EduTrust's, because the year view is.
 */
class DaybookExport implements WithMultipleSheets
{
    use Exportable;

    /**
     * @param  array<string, mixed>  $book  DaybookService::book()
     * @param  array<string, mixed>  $year  DaybookService::analysis()
     * @param  array<string, mixed>|null  $summary  DaybookService::monthlySummary()
     */
    public function __construct(
        private ?SchoolSetting $school,
        private Carbon $month,
        private array $book,
        private string $yearLabel,
        private array $year,
        private ?Budget $sheet = null,
        private ?array $summary = null,
    ) {}

    public function sheets(): array
    {
        $school = $this->school?->school_name ?? config('app.name');

        $sheets = [
            new DaybookMonthSheet($school, $this->month, $this->book),
            new DaybookYearSheet($school, $this->yearLabel, $this->year),
        ];

        if ($this->sheet && $this->summary) {
            $sheets[] = new DaybookSummarySheet($school, $this->month, $this->sheet, $this->summary);
        }

        return $sheets;
    }
}
