<?php

namespace App\DTOs;

use App\Models\Payslip;
use Illuminate\Support\Collection;

/**
 * PF/SSF contributions due for remittance over a period, as produced by
 * PayrollComplianceReportService::pfSsfRemittance(). One line per Payslip —
 * every figure here (pf_employee, pf_employer, ssf_employee, ssf_employer)
 * already exists as a frozen column on Payslip (PayrollCalculationService),
 * so unlike VatRegisterLine there is nothing to compute per line; this DTO
 * only carries the pre-summed totals.
 */
final readonly class PfSsfRemittanceReport
{
    /**
     * @param  Collection<int, Payslip>  $payslips
     */
    public function __construct(
        public Collection $payslips,
        public float $totalPfEmployee,
        public float $totalPfEmployer,
        public float $totalSsfEmployee,
        public float $totalSsfEmployer,
    ) {}

    public function totalPf(): float
    {
        return round($this->totalPfEmployee + $this->totalPfEmployer, 2);
    }

    public function totalSsf(): float
    {
        return round($this->totalSsfEmployee + $this->totalSsfEmployer, 2);
    }

    public function grandTotal(): float
    {
        return round($this->totalPf() + $this->totalSsf(), 2);
    }
}
