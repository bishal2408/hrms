<?php

namespace App\DTOs;

use App\Models\Payslip;
use Illuminate\Support\Collection;

/**
 * TDS withheld over a period, as produced by
 * PayrollComplianceReportService::tds(). One line per Payslip — taxable_income
 * and tds already exist as frozen columns (PayrollCalculationService), same
 * "nothing to compute per line" reasoning as PfSsfRemittanceReport.
 */
final readonly class TdsReport
{
    /**
     * @param  Collection<int, Payslip>  $payslips
     */
    public function __construct(
        public Collection $payslips,
        public float $totalTaxableIncome,
        public float $totalTds,
    ) {}
}
