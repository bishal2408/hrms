<?php

namespace App\Exceptions;

use RuntimeException;

/** Thrown when InvoiceService or PayrollRunService needs a default GL account (AR, Sales Revenue, VAT Payable, Salary Expense, Salary Payable, Statutory Payable) that Company Settings hasn't configured yet. */
class MissingAccountingConfigurationException extends RuntimeException {}
