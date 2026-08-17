<?php

namespace App\Exceptions;

use RuntimeException;

/** Thrown when payroll is calculated for an employee with no salary structure effective as of the pay period. */
class MissingSalaryStructureException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This employee has no salary structure effective as of this pay period.');
    }
}
