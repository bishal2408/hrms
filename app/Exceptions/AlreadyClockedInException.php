<?php

namespace App\Exceptions;

use RuntimeException;

/** Thrown when an employee tries to clock in while already clocked in today. */
class AlreadyClockedInException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Already clocked in today.');
    }
}
