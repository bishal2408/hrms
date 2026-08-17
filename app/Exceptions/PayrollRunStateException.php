<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown whenever a PayrollRun is not in the state a requested action needs:
 * recalculating/discarding/finalizing something that isn't a draft, or
 * adjusting a payslip whose run isn't finalized yet. One class covers all of
 * these — they are the same underlying problem (wrong state for the action),
 * not distinct business rules the way the leave-request exceptions are.
 */
class PayrollRunStateException extends RuntimeException {}
