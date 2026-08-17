<?php

namespace App\Exceptions;

use RuntimeException;

/** Thrown when approving, rejecting or cancelling a request that has already been decided. */
class LeaveRequestNotPendingException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This request has already been decided.');
    }
}
