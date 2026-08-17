<?php

namespace App\Exceptions;

use RuntimeException;

/** Thrown when a leave request would exceed the employee's remaining balance for that leave type. */
class LeaveBalanceExceededException extends RuntimeException
{
    public function __construct(int $requested, int $remaining)
    {
        parent::__construct("Requested {$requested} day(s), but only {$remaining} day(s) remain for this leave type this fiscal year.");
    }
}
