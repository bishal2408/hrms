<?php

namespace App\DTOs;

use Illuminate\Support\Collection;

/**
 * The full VAT register for a period, as produced by
 * AccountingReportService::vatRegister(). Totals are pre-computed
 * (excluding cancelled invoices) rather than re-derived in the view.
 */
final readonly class VatRegisterReport
{
    /**
     * @param  Collection<int, VatRegisterLine>  $lines
     */
    public function __construct(
        public Collection $lines,
        public float $totalTaxable,
        public float $totalExempt,
        public float $totalVat,
    ) {}

    public function totalSales(): float
    {
        return round($this->totalTaxable + $this->totalExempt, 2);
    }
}
