<?php

namespace App\Exceptions;

use RuntimeException;

/** Thrown when InvoiceService::create() is called with no line items. */
class EmptyInvoiceException extends RuntimeException {}
