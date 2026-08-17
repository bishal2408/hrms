<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a user acts on a leave request they have no standing over —
 * approving/rejecting someone they don't manage (or their own request, which
 * nobody may approve regardless of role), or cancelling a request that isn't
 * theirs.
 */
class LeaveRequestForbiddenException extends RuntimeException
{
    public function __construct(string $action = 'act on')
    {
        parent::__construct("You may not {$action} this leave request.");
    }
}
