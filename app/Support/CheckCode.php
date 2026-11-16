<?php

namespace App\Support;

/**
 * The code printed on a paper the school issues (a receipt, a certificate)
 * so anyone holding it can check it on the school's public page.
 *
 * Twelve characters from an alphabet with no 0/O or 1/I/L, so a code read
 * off paper and typed back in cannot be misread; about 59 bits, so the
 * papers cannot be walked by guessing.
 */
class CheckCode
{
    public const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    /** @param  callable(string): bool  $taken  whether a code is already in use */
    public static function generate(callable $taken): string
    {
        do {
            $code = '';
            for ($i = 0; $i < 12; $i++) {
                $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
        } while ($taken($code));

        return $code;
    }

    /** What someone typed, as it is stored: capitals, no spaces or dashes. */
    public static function normalise(?string $typed): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $typed));
    }

    /** As printed: XXXX-XXXX-XXXX. */
    public static function forPrint(string $code): string
    {
        return implode('-', str_split($code, 4));
    }
}
