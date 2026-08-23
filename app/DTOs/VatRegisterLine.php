<?php

namespace App\DTOs;

use App\Models\Invoice;

/**
 * One row of the VAT register, as produced by
 * AccountingReportService::vatRegister(). Cancelled invoices still appear
 * as a line (for audit continuity — cancel-don't-delete) but are excluded
 * from the report's totals.
 */
final readonly class VatRegisterLine
{
    public function __construct(
        public Invoice $invoice,
        public float $taxableAmount,
        public float $exemptAmount,
    ) {}
}
