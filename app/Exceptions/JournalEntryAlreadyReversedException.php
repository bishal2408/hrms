<?php

namespace App\Exceptions;

use RuntimeException;

/** Thrown when JournalEntryService::reverse() is called on an entry that already has a reversal posted against it. */
class JournalEntryAlreadyReversedException extends RuntimeException {}
