<?php

namespace App\Exceptions;

use RuntimeException;

/** Thrown when an invoice has a VAT-applicable line but no VatRate is configured for the issue date — never silently charged as 0%. */
class MissingVatRateException extends RuntimeException {}
