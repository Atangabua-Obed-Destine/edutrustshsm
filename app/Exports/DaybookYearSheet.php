<?php

namespace App\Exports;

use App\Exports\Concerns\StylesBookSheet;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;

/** The year month by month: in, out, net and the balance at each month end. */
class DaybookYearSheet implements FromArray, WithTitle, WithColumnWidths, WithEvents
{
    use StylesBookSheet;

    public function __construct(private string $school, private string $yearLabel, private array $year) {}

    public function title(): string
    {
        return $this->tabName(__('The year :year', ['year' => $this->yearLabel]));
    }

    public function array(): array
    {
        $rows = $this->titleBlock($this->school, mb_strtoupper(__('The year in cash')), $this->yearLabel);

        $this->headerRow = count($rows) + 1;
        $rows[] = [__('Month'), __('In'), __('Of which fees'), __('Out'), __('Net'), __('Balance')];
        $rows[] = [__('Opening balance'), null, null, null, null, (float) $this->year['opening']];

        foreach ($this->year['months'] as $m) {
            $rows[] = [$m['label'], (float) $m['in'], (float) $m['fees'], (float) $m['out'], (float) $m['net'], (float) $m['balance']];
        }

        $rows[] = [__('The year'), (float) $this->year['total_in'], (float) collect($this->year['months'])->sum('fees'),
            (float) $this->year['total_out'], (float) $this->year['net'], (float) $this->year['closing']];
        $this->totalRows[] = count($rows);

        return $rows;
    }

    public function columnWidths(): array
    {
        return ['A' => 18, 'B' => 15, 'C' => 15, 'D' => 15, 'E' => 15, 'F' => 15];
    }

    protected function moneyColumns(): array
    {
        return ['B', 'C', 'D', 'E', 'F'];
    }

    protected function lastColumn(): string
    {
        return 'F';
    }
}
