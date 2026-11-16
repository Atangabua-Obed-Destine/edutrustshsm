<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Any list screen as a workbook (App\Support\Listing): the school and the
 * list's name on top, a dark header row that stays in view, amounts as
 * numbers with thousands separators.
 */
class ListExport implements FromArray, ShouldAutoSize, WithEvents, WithTitle
{
    private const HEADER_ROW = 5;

    /**
     * @param  array{0: string, 1: string, 2: string}  $heading  school, title, filters and date
     * @param  array<int, string>  $headings
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array<int, bool>  $money  which columns hold amounts
     */
    public function __construct(
        private array $heading,
        private array $headings,
        private array $rows,
        private array $money,
    ) {}

    public function array(): array
    {
        return [
            [$this->heading[0]],
            [$this->heading[1]],
            [$this->heading[2]],
            [''],
            $this->headings,
            ...$this->rows,
        ];
    }

    public function title(): string
    {
        // Excel refuses sheet names over 31 characters or with []:*?/\
        return mb_substr(preg_replace('#[\[\]:*?/\\\\]#', ' ', $this->heading[1]), 0, 31);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $last = Coordinate::stringFromColumnIndex(max(1, count($this->headings)));
                $header = 'A'.self::HEADER_ROW.":{$last}".self::HEADER_ROW;
                $lastRow = self::HEADER_ROW + count($this->rows);

                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
                $sheet->getStyle('A3')->getFont()->setItalic(true)->getColor()->setRGB('555555');
                $sheet->getStyle($header)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle($header)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F3A5F');

                foreach ($this->money as $i => $isMoney) {
                    if (! $isMoney || $lastRow <= self::HEADER_ROW) {
                        continue;
                    }
                    $col = Coordinate::stringFromColumnIndex($i + 1);
                    $sheet->getStyle($col.(self::HEADER_ROW + 1).":{$col}{$lastRow}")->getNumberFormat()->setFormatCode('#,##0');
                    $sheet->getStyle($col.self::HEADER_ROW.":{$col}{$lastRow}")->getAlignment()->setHorizontal('right');
                }

                $sheet->freezePane('A'.(self::HEADER_ROW + 1));
                $sheet->setAutoFilter("A".self::HEADER_ROW.":{$last}".max($lastRow, self::HEADER_ROW));
            },
        ];
    }
}
