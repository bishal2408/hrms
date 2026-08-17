<?php

namespace App\Exceptions;

use RuntimeException;

/** Thrown when an employee tries to clock out without an open clock-in today. */
class NotClockedInException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Not clocked in today.');
    }
}
