<?php

namespace App\Exports;

use App\Exports\Concerns\StylesBookSheet;
use App\Models\Budget;
use App\Models\BudgetLine;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;

/** The month against the Income & Expenditure sheet: Budget · Monthly · B/F · Cumulative · Balance. */
class DaybookSummarySheet implements FromArray, WithTitle, WithColumnWidths, WithEvents
{
    use StylesBookSheet;

    public function __construct(private string $school, private Carbon $month, private Budget $sheet, private array $summary) {}

    public function title(): string
    {
        return $this->tabName(__('Monthly Summary'));
    }

    public function array(): array
    {
        $rows = $this->titleBlock($this->school, mb_strtoupper(__('Monthly Summary')), $this->month->translatedFormat('F Y').' · '.$this->sheet->title);

        $this->headerRow = count($rows) + 1;
        $rows[] = [__('Code'), __('Description'), __('Budget'), __('Monthly'), __('B/F'), __('Cumulative'), __('Balance')];

        $labels = [
            BudgetLine::SECTION_INCOME => __('Income'),
            BudgetLine::SECTION_EXPENDITURE => __('Expenditure'),
            BudgetLine::SECTION_CAPITAL => __('Capital'),
        ];

        foreach (BudgetLine::SECTIONS as $section) {
            $rows[] = ['', mb_strtoupper($labels[$section])];
            $this->totalRows[] = count($rows);

            foreach ($this->summary['lines'] as $row) {
                if ($row['line']->section !== $section) {
                    continue;
                }
                $rows[] = [
                    $row['line']->code,
                    ($row['line']->is_header ? '' : '    ').$row['line']->displayName(),
                    (float) $row['budget'], (float) $row['monthly'], (float) $row['brought'], (float) $row['cumulative'],
                    $row['budget'] != 0 ? (float) $row['balance'] : null,
                ];
            }

            $t = $this->summary['totals'][$section];
            $rows[] = ['', __('Total :section', ['section' => $labels[$section]]),
                (float) $t['budget'], (float) $t['monthly'], (float) $t['brought'], (float) $t['cumulative'], (float) ($t['budget'] - $t['cumulative'])];
            $this->totalRows[] = count($rows);
        }

        $in = $this->summary['totals'][BudgetLine::SECTION_INCOME];
        $out = $this->summary['totals'][BudgetLine::SECTION_EXPENDITURE];
        $rows[] = ['', __('NET BALANCE').' ('.__('income − expenditure').')',
            (float) ($in['budget'] - $out['budget']), (float) ($in['monthly'] - $out['monthly']),
            (float) ($in['brought'] - $out['brought']), (float) ($in['cumulative'] - $out['cumulative']), null];
        $this->totalRows[] = count($rows);

        return $rows;
    }

    public function columnWidths(): array
    {
        return ['A' => 9, 'B' => 46, 'C' => 15, 'D' => 15, 'E' => 15, 'F' => 15, 'G' => 15];
    }

    protected function moneyColumns(): array
    {
        return ['C', 'D', 'E', 'F', 'G'];
    }

    protected function lastColumn(): string
    {
        return 'G';
    }
}
