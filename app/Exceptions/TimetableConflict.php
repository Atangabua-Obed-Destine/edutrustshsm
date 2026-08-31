<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised inside the save transaction so a clash rolls the write back rather
 * than leaving the day half-rewritten.
 */
class TimetableConflict extends RuntimeException
{
    /** @param array<int, string> $conflicts */
    public function __construct(public readonly array $conflicts)
    {
        parent::__construct(implode(' ', $conflicts));
    }
}
