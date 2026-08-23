<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a journal entry's lines don't sum to debits == credits, or a
 * line has both/neither of debit and credit set — the entry is never
 * written to the database (JournalEntryService::post() validates before
 * opening the transaction).
 */
class UnbalancedJournalEntryException extends RuntimeException {}
