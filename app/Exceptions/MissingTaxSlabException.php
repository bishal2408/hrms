<?php

namespace App\Exceptions;

use RuntimeException;

/** Thrown when a payroll calculation needs a TaxSlab table that has not been configured for the employee's marital status as of the calculation date. */
class MissingTaxSlabException extends RuntimeException
{
    public function __construct(string $maritalStatus)
    {
        parent::__construct("No tax slab table is configured for '{$maritalStatus}' employees for this pay period. Add one in Setup before running payroll.");
    }
}
