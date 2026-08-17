<?php

namespace App\Services;

use App\DTOs\PayrollCalculationResult;
use App\Exceptions\MissingPayrollRateException;
use App\Exceptions\MissingSalaryStructureException;
use App\Exceptions\MissingTaxSlabException;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\PayrollRate;
use App\Models\SalaryComponentType;
use App\Models\SalaryStructure;
use App\Models\TaxSlab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Computes one employee's pay for one period. This is the only place the
 * payroll formula exists — Phase 3b's payroll run and payslip both consume
 * PayrollCalculationResult rather than recomputing any of this.
 *
 * The formula (decisions made 2026-08-17, all worth re-reading before
 * touching this class):
 *
 * - **Attendance/leave affect basic salary only.** A day in the period counts
 *   as paid if the employee has an AttendanceLog row for it, OR it falls
 *   inside an approved LeaveRequest whose LeaveType is paid. Every other day
 *   is unpaid and basic salary is pro-rated: basicAfterAttendance =
 *   basicSalary × (totalDays − unpaidDays) / totalDays. There is no company
 *   holiday/weekly-off calendar yet, so every calendar day in the period
 *   currently counts toward totalDays — a documented limitation, not an
 *   oversight (see docs/ROADMAP.md 3a).
 * - **Allowance/deduction line items are flat.** They come from the
 *   employee's current SalaryStructure and are not affected by attendance —
 *   a transport allowance or a loan instalment doesn't change because of a
 *   day off.
 * - **PF/SSF are computed on basicAfterAttendance** (the attendance-adjusted
 *   basic, not the structure's raw basic and not gross-including-allowances).
 * - **Taxable income = grossPay − pfEmployee − ssfEmployee.** Component
 *   deductions (e.g. loan repayment) are treated as post-tax and do not
 *   reduce taxable income.
 * - **TDS uses the annualize-and-divide method**: taxableIncome × 12 → apply
 *   TaxSlab's annual brackets for the employee's marital status → divide the
 *   resulting annual tax by 12. Deliberately not a cumulative
 *   year-to-date method (decision: 2026-08-17) — appropriate for now since
 *   the slab rates themselves are still placeholder values, not verified
 *   legal figures (CLAUDE.md).
 */
class PayrollCalculationService
{
    /**
     * @throws MissingSalaryStructureException
     * @throws MissingPayrollRateException
     * @throws MissingTaxSlabException
     */
    public function calculate(Employee $employee, Carbon $periodStart, Carbon $periodEnd): PayrollCalculationResult
    {
        $structure = SalaryStructure::currentFor($employee->id, $periodEnd);

        if ($structure === null) {
            throw new MissingSalaryStructureException;
        }

        $totalDays = (int) $periodStart->diffInDays($periodEnd) + 1;
        $unpaidDays = $totalDays - $this->paidDayCount($employee, $periodStart, $periodEnd);

        $basicAfterAttendance = $totalDays > 0
            ? round($structure->basic_salary * ($totalDays - $unpaidDays) / $totalDays, 2)
            : 0.0;

        $allowanceItems = $this->itemsFor($structure, SalaryComponentType::TYPE_ALLOWANCE);
        $deductionItems = $this->itemsFor($structure, SalaryComponentType::TYPE_DEDUCTION);

        $allowancesTotal = round(collect($allowanceItems)->sum('amount'), 2);
        $deductionsTotal = round(collect($deductionItems)->sum('amount'), 2);

        $grossPay = round($basicAfterAttendance + $allowancesTotal, 2);

        [$pfEmployee, $pfEmployer] = $this->contribution(PayrollRate::TYPE_PROVIDENT_FUND, $basicAfterAttendance, $periodEnd);
        [$ssfEmployee, $ssfEmployer] = $this->contribution(PayrollRate::TYPE_SOCIAL_SECURITY_FUND, $basicAfterAttendance, $periodEnd);

        $taxableIncome = round($grossPay - $pfEmployee - $ssfEmployee, 2);
        $tds = $this->monthlyTds($employee, $taxableIncome, $periodEnd);

        $netPay = round($grossPay - $deductionsTotal - $pfEmployee - $ssfEmployee - $tds, 2);

        return new PayrollCalculationResult(
            employeeId: $employee->id,
            periodStart: $periodStart,
            periodEnd: $periodEnd,
            basicSalary: (float) $structure->basic_salary,
            totalDays: $totalDays,
            unpaidDays: $unpaidDays,
            basicAfterAttendance: $basicAfterAttendance,
            allowanceItems: $allowanceItems,
            deductionItems: $deductionItems,
            allowancesTotal: $allowancesTotal,
            deductionsTotal: $deductionsTotal,
            grossPay: $grossPay,
            pfEmployee: $pfEmployee,
            pfEmployer: $pfEmployer,
            ssfEmployee: $ssfEmployee,
            ssfEmployer: $ssfEmployer,
            taxableIncome: $taxableIncome,
            tds: $tds,
            netPay: $netPay,
        );
    }

    /**
     * Count of days in the period that are either worked or covered by an
     * approved, paid leave request. A day satisfying both counts once — this
     * is a set union, not a sum, so a worked day that a leave request also
     * happens to overlap is never double-counted.
     */
    private function paidDayCount(Employee $employee, Carbon $periodStart, Carbon $periodEnd): int
    {
        // whereDate(), not whereBetween()/where('col','<=',...): SQLite
        // stores a `date`-cast column as '2026-07-30 00:00:00', which
        // string-compares as *greater than* a plain '2026-07-30' bound and
        // silently excludes that boundary day. MySQL's native DATE column
        // never had this problem — whereDate() is correct on both.
        $paidDates = AttendanceLog::query()
            ->where('employee_id', $employee->id)
            ->whereDate('date', '>=', $periodStart->toDateString())
            ->whereDate('date', '<=', $periodEnd->toDateString())
            ->pluck('date')
            ->map(fn (Carbon $date): string => $date->toDateString());

        $leaves = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->whereHas('leaveType', fn (Builder $query) => $query->where('is_paid', true))
            ->whereDate('start_date', '<=', $periodEnd->toDateString())
            ->whereDate('end_date', '>=', $periodStart->toDateString())
            ->get(['start_date', 'end_date']);

        $leaveDates = collect();

        foreach ($leaves as $leave) {
            $rangeStart = $leave->start_date->gt($periodStart) ? $leave->start_date : $periodStart;
            $rangeEnd = $leave->end_date->lt($periodEnd) ? $leave->end_date : $periodEnd;

            for ($date = $rangeStart->copy(); $date->lte($rangeEnd); $date->addDay()) {
                $leaveDates->push($date->toDateString());
            }
        }

        return $paidDates->merge($leaveDates)->unique()->count();
    }

    /**
     * @return array<int, array{name: string, amount: float}>
     */
    private function itemsFor(SalaryStructure $structure, string $componentType): array
    {
        return $structure->items
            ->filter(fn ($item) => $item->componentType->component_type === $componentType)
            ->map(fn ($item) => [
                'name' => $item->componentType->name,
                'amount' => (float) $item->amount,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{0: float, 1: float} [employee amount, employer amount]
     *
     * @throws MissingPayrollRateException
     */
    private function contribution(string $type, float $base, Carbon $asOf): array
    {
        $rate = PayrollRate::currentFor($type, $asOf);

        if ($rate === null) {
            throw new MissingPayrollRateException($type);
        }

        return [
            round($base * ((float) $rate->employee_contribution_percent / 100), 2),
            round($base * ((float) $rate->employer_contribution_percent / 100), 2),
        ];
    }

    /**
     * @throws MissingTaxSlabException
     */
    private function monthlyTds(Employee $employee, float $monthlyTaxableIncome, Carbon $asOf): float
    {
        $slabs = TaxSlab::slabsFor($employee->marital_status, $asOf);

        if ($slabs->isEmpty()) {
            throw new MissingTaxSlabException($employee->marital_status);
        }

        $annualTax = $this->annualTaxFor($slabs, $monthlyTaxableIncome * 12);

        return round($annualTax / 12, 2);
    }

    /** @param  Collection<int, TaxSlab>  $slabs  Ascending by lower_bound (TaxSlab::slabsFor()'s contract). */
    private function annualTaxFor(Collection $slabs, float $annualIncome): float
    {
        $tax = 0.0;

        foreach ($slabs as $slab) {
            $lower = (float) $slab->lower_bound;

            if ($annualIncome <= $lower) {
                break;
            }

            $upper = $slab->upper_bound === null ? null : (float) $slab->upper_bound;
            $bracketTop = $upper === null ? $annualIncome : min($upper, $annualIncome);

            $tax += max(0.0, $bracketTop - $lower) * ((float) $slab->rate_percent / 100);
        }

        return round($tax, 2);
    }
}
