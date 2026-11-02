<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Reading the spreadsheets schools actually produce.
 *
 * Excel in French saves with semicolons and in the old Windows encoding;
 * Excel in English with commas and sometimes a byte-order mark; either may
 * keep a "sep=" line. Dates are written day first, as in Cameroon.
 */
class SchoolCsv
{
    /**
     * @param  array<string, string>  $aliases  heading people write => our column name
     * @return array{0: list<string>, 1: array<int, list<string>>} headers, and rows keyed by line number
     */
    public static function read(string $path, array $aliases = []): array
    {
        $text = (string) file_get_contents($path);
        $text = (string) preg_replace('/^\xEF\xBB\xBF/', '', $text);
        if (! mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
        }

        $line = 0;
        if (preg_match('/^"?sep=(.)"?\r?\n/i', $text, $m)) {
            $delimiter = $m[1];
            $text = substr($text, strlen($m[0]));
            $line = 1;
        } else {
            $head = strtok($text, "\r\n") ?: '';
            $delimiter = substr_count($head, ';') > substr_count($head, ',') ? ';' : ',';
        }

        // Parsed as a stream, so a quoted value holding a comma or a line
        // break stays one value.
        $handle = fopen('php://temp', 'w+');
        fwrite($handle, $text);
        rewind($handle);

        $headers = [];
        $rows = [];
        while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            $line++;
            if ($headers === []) {
                $headers = array_map(function ($h) use ($aliases) {
                    $key = str_replace([' ', '-', '.'], '_', mb_strtolower(trim((string) $h)));
                    $key = rtrim($key, '*_');

                    return $aliases[$key] ?? $key;
                }, $row);

                continue;
            }
            $rows[$line] = $row;
        }
        fclose($handle);

        return [$headers, $rows];
    }

    /**
     * One row as column => trimmed value.
     *
     * @param  list<string>  $headers
     * @param  list<string>  $row
     * @return array<string, string>
     */
    public static function combine(array $headers, array $row): array
    {
        $values = array_map(fn ($v) => trim((string) $v), array_slice(array_pad($row, count($headers), ''), 0, count($headers)));

        return array_combine($headers, $values);
    }

    /** 15/05/2018, 15-05-2018, 15.05.2018, 15/05/18 or 2018-05-15. Day first: this is Cameroon. */
    public static function date(string $value): ?Carbon
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        // Two-digit years first: "Y" would read 15/05/18 as the year 18.
        foreach (['Y-m-d', 'd/m/y', 'd/m/Y', 'd-m-y', 'd-m-Y', 'd.m.Y', 'Y/m/d'] as $format) {
            $date = \DateTime::createFromFormat('!'.$format, $value);
            $problems = \DateTime::getLastErrors();
            if ($date && ($problems === false || ($problems['warning_count'] === 0 && $problems['error_count'] === 0))) {
                return Carbon::instance($date);
            }
        }

        return null;
    }

    /** 25000, 25 000, 25.000, 25,000 or "25 000 FCFA": francs have no decimals. */
    public static function amount(string $value): ?float
    {
        $digits = preg_replace('/[^\d]/', '', preg_replace('/[.,]\d{1,2}$/', '', trim($value)));

        return $digits === '' ? null : (float) $digits;
    }
}
