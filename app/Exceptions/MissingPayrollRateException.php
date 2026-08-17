<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a payroll calculation needs a PayrollRate (PF/SSF) that has not
 * been configured as of the calculation date. Failing loudly here matters:
 * silently treating a missing rate as 0% would produce a payslip that looks
 * correct but pays nobody's statutory contribution.
 */
class MissingPayrollRateException extends RuntimeException
{
    public function __construct(string $type)
    {
        parent::__construct("No {$type} rate is configured for this pay period. Add one in Setup before running payroll.");
    }
}
