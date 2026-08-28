<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised inside a transaction when a submission turns out to have been reviewed
 * already — used to roll the whole approval back rather than record a duplicate.
 */
class AlreadyReviewed extends RuntimeException
{
}
