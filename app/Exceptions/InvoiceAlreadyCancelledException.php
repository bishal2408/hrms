<?php

namespace App\Exceptions;

use RuntimeException;

/** Thrown when InvoiceService::cancel() is called on an invoice that's already cancelled. */
class InvoiceAlreadyCancelledException extends RuntimeException {}
