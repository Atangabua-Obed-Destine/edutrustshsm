<?php

namespace App\Exports\Concerns;

use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * One look for every tab of the cash-book workbook: a title block, a dark
 * header row, money right-aligned with thousands separators, and bold totals.
 */
trait StylesBookSheet
{
    protected int $headerRow = 0;

    /** @var array<int, int> */
    protected array $totalRows = [];

    /** The columns that hold money, as letters. */
    abstract protected function moneyColumns(): array;

    abstract protected function lastColumn(): string;

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $last = $this->lastColumn();
                $lastRow = $sheet->getHighestRow();

                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
                $sheet->getStyle('A3:A4')->getFont()->setItalic(true)->getColor()->setRGB('555555');

                $header = "A{$this->headerRow}:{$last}{$this->headerRow}";
                $sheet->getStyle($header)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle($header)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F3A5F');

                foreach ($this->moneyColumns() as $column) {
                    $sheet->getStyle("{$column}".($this->headerRow + 1).":{$column}{$lastRow}")
                        ->getNumberFormat()->setFormatCode('#,##0');
                    $sheet->getStyle("{$column}{$this->headerRow}:{$column}{$lastRow}")
                        ->getAlignment()->setHorizontal('right');
                }

                foreach ($this->totalRows as $row) {
                    $sheet->getStyle("A{$row}:{$last}{$row}")->getFont()->setBold(true);
                    $sheet->getStyle("A{$row}:{$last}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EEF2F7');
                }

                $sheet->freezePane('A'.($this->headerRow + 1));
            },
        ];
    }

    /** @return array<int, array<int, string>> */
    protected function titleBlock(string $school, string $title, string $period): array
    {
        return [
            [$school],
            [$title],
            [$period],
            [__('Exported :when · Amounts in FCFA', ['when' => now()->format('d/m/Y H:i')])],
            [''],
        ];
    }

    /** Excel forbids : \ / ? * [ ] in a tab name and stops at 31 characters. */
    protected function tabName(string $name): string
    {
        return mb_substr(preg_replace('~[:\\\\/?*\[\]]~', '-', $name), 0, 31);
    }
}
