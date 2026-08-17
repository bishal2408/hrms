<?php

namespace App\Exceptions;

use RuntimeException;

/** Thrown when a new request's date range overlaps an existing pending/approved request. */
class OverlappingLeaveRequestException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This overlaps a pending or approved leave request that already exists.');
    }
}
