<?php

namespace App\Services;

use App\DTOs\PfSsfRemittanceReport;
use App\DTOs\TdsReport;
use App\Models\PayrollRun;
use App\Models\Payslip;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Read-only payroll compliance reports — PF/SSF remittance and TDS. Lives
 * separately from AccountingReportService (VAT/trial balance): those are
 * general-ledger/sales-document concepts, these are payroll concepts read
 * from Payslip, the same "which domain does this report actually belong to"
 * reasoning AccountingReportService's own docblock already applies to why
 * vatRegister() reads Invoice instead of the ledger.
 *
 * Both reports only include payslips belonging to a **finalized** run — a
 * draft's numbers aren't real yet (same rule PayslipResource already
 * enforces for the employee's own payslip visibility).
 */
class PayrollComplianceReportService
{
    /**
     * PF and SSF contributions (both employee-withheld and employer-paid)
     * due for remittance, for every finalized payslip whose run's period
     * falls in [$from, $until].
     */
    public function pfSsfRemittance(?Carbon $from = null, ?Carbon $until = null): PfSsfRemittanceReport
    {
        $payslips = $this->finalizedPayslipsInRange($from, $until);

        return new PfSsfRemittanceReport(
            payslips: $payslips,
            totalPfEmployee: round((float) $payslips->sum('pf_employee'), 2),
            totalPfEmployer: round((float) $payslips->sum('pf_employer'), 2),
            totalSsfEmployee: round((float) $payslips->sum('ssf_employee'), 2),
            totalSsfEmployer: round((float) $payslips->sum('ssf_employer'), 2),
        );
    }

    /**
     * TDS withheld for every finalized payslip whose run's period falls in
     * [$from, $until].
     */
    public function tds(?Carbon $from = null, ?Carbon $until = null): TdsReport
    {
        $payslips = $this->finalizedPayslipsInRange($from, $until);

        return new TdsReport(
            payslips: $payslips,
            totalTaxableIncome: round((float) $payslips->sum('taxable_income'), 2),
            totalTds: round((float) $payslips->sum('tds'), 2),
        );
    }

    /** @return Collection<int, Payslip> */
    private function finalizedPayslipsInRange(?Carbon $from, ?Carbon $until): Collection
    {
        return Payslip::query()
            ->with(['employee', 'payrollRun'])
            ->whereHas('payrollRun', function (Builder $query) use ($from, $until): void {
                $query->where('status', PayrollRun::STATUS_FINALIZED);

                // whereDate(), not whereBetween()/plain <=: SQLite stores a
                // `date`-cast column with a time suffix that string-compares
                // wrong against a bare date bound (CLAUDE.md's own
                // established convention throughout this codebase).
                if ($from !== null) {
                    $query->whereDate('period_start', '>=', $from->toDateString());
                }

                if ($until !== null) {
                    $query->whereDate('period_end', '<=', $until->toDateString());
                }
            })
            ->get()
            // sortBy()'s multi-criteria [callback, direction] form expects
            // each callback to be a genuine two-argument comparator
            // (`fn ($a, $b) => $a <=> $b`), not a single-argument key
            // extractor — passing the latter silently produces wrong,
            // effectively arbitrary ordering (PHP ignores the unused second
            // parameter rather than erroring).
            ->sortBy([
                [fn (Payslip $a, Payslip $b): int => $a->payrollRun->period_start <=> $b->payrollRun->period_start, 'asc'],
                [fn (Payslip $a, Payslip $b): int => $a->employee->full_name <=> $b->employee->full_name, 'asc'],
            ])
            ->values();
    }
}
