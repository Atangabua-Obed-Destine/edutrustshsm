<?php

namespace App\Exports;

use App\Exports\Concerns\StylesBookSheet;
use App\Services\DaybookService;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;

/** The month of movements, as the book prints it, with figures Excel can add up. */
class DaybookMonthSheet implements FromArray, WithTitle, WithColumnWidths, WithEvents
{
    use StylesBookSheet;

    public function __construct(private string $school, private Carbon $month, private array $book) {}

    public function title(): string
    {
        return $this->tabName($this->month->translatedFormat('F Y'));
    }

    public function array(): array
    {
        $rows = $this->titleBlock($this->school, mb_strtoupper(__('Cash Book')), $this->month->translatedFormat('F Y'));

        $this->headerRow = count($rows) + 1;
        $rows[] = [__('Date'), __('Reference'), __('Description'), __('Party'), __('Method'), __('Heading'), __('Analysis'), __('In'), __('Out'), __('Balance')];
        $rows[] = [$this->month->copy()->startOfMonth()->format('d/m/Y'), '', __('Balance brought forward'), '', '', '', '', null, null, (float) $this->book['brought_forward']];

        foreach ($this->book['rows'] as $row) {
            $rows[] = [
                Carbon::parse($row['date'])->format('d/m/Y'),
                $row['ref'],
                $row['description'],
                $row['party'] ?? '',
                $row['method'] ?? '',
                $row['heading'],
                $row['unanalysed'] > 0 ? trim(($row['analysis'] ?? '').' · '.__('not on the sheet'), ' ·') : ($row['analysis'] ?? ''),
                $row['direction'] === DaybookService::IN ? (float) $row['amount'] : null,
                $row['direction'] === DaybookService::OUT ? (float) $row['amount'] : null,
                (float) $row['balance'],
            ];
        }

        $rows[] = ['', '', __('Totals for :month', ['month' => $this->month->translatedFormat('F Y')]), '', '', '', '',
            (float) $this->book['total_in'], (float) $this->book['total_out'], (float) $this->book['closing']];
        $this->totalRows[] = count($rows);

        return $rows;
    }

    public function columnWidths(): array
    {
        return ['A' => 12, 'B' => 14, 'C' => 44, 'D' => 22, 'E' => 14, 'F' => 22, 'G' => 18, 'H' => 14, 'I' => 14, 'J' => 15];
    }

    protected function moneyColumns(): array
    {
        return ['H', 'I', 'J'];
    }

    protected function lastColumn(): string
    {
        return 'J';
    }
}
