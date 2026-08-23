<?php

namespace App\Services;

use App\Exceptions\MissingAccountingConfigurationException;
use App\Exceptions\MissingPayrollRateException;
use App\Exceptions\MissingSalaryStructureException;
use App\Exceptions\MissingTaxSlabException;
use App\Exceptions\PayrollRunStateException;
use App\Models\Company;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\PayslipAdjustment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The payroll run lifecycle: run (draft) → recalculate (still draft) →
 * finalize (locked), with PayslipAdjustment as the only way a finalized
 * payslip's effective pay changes afterward.
 *
 * Every write to a PayrollRun or Payslip goes through this service — neither
 * Filament resource touches those models directly — so "a draft may be
 * recalculated or discarded, a finalized run may not be touched at all"
 * exists in exactly one place.
 */
class PayrollRunService
{
    public function __construct(
        private readonly PayrollCalculationService $calculator,
        private readonly JournalEntryService $journalEntryService,
    ) {}

    /**
     * Calculate payroll for every employee whose employment overlaps the
     * period. An employee missing salary/PF/SSF/tax configuration is skipped
     * (recorded on the run, not silently dropped) rather than failing the
     * whole run — one misconfigured employee should not block payroll for
     * everyone else.
     *
     * @throws PayrollRunStateException When a run for this exact period already exists.
     */
    public function run(Carbon $periodStart, Carbon $periodEnd, User $creator): PayrollRun
    {
        if (PayrollRun::query()->whereDate('period_start', $periodStart)->whereDate('period_end', $periodEnd)->exists()) {
            throw new PayrollRunStateException('A payroll run for this exact period already exists.');
        }

        // Not create(): 'created_by' is deliberately outside $fillable so no
        // form can mass-assign who ran payroll — this service is the only
        // place it's set.
        $run = new PayrollRun([
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
        ]);
        $run->created_by = $creator->id;
        $run->save();

        $this->populate($run);

        return $run->refresh();
    }

    /**
     * Redo a draft run's calculation from scratch — every existing payslip is
     * discarded and recreated. Not available once finalized.
     *
     * @throws PayrollRunStateException
     */
    public function recalculate(PayrollRun $run): PayrollRun
    {
        $this->assertDraft($run);

        DB::transaction(function () use ($run): void {
            $run->payslips()->delete();
            $this->populate($run);
        });

        return $run->refresh();
    }

    /**
     * Lock a draft run. Every Payslip in it becomes immutable from this point
     * on — corrections happen through addAdjustment(), never by editing a row
     * here. Also posts the run's aggregate payroll expense to the general
     * ledger (see postToLedger()) — finalizing is the moment payroll becomes
     * a real financial event, the same reasoning InvoiceService posts at
     * issue time rather than draft time.
     *
     * @throws PayrollRunStateException
     * @throws MissingAccountingConfigurationException
     */
    public function finalize(PayrollRun $run, User $user): PayrollRun
    {
        $this->assertDraft($run);

        if ($run->payslips()->doesntExist()) {
            throw new PayrollRunStateException('This run has no payslips to finalize — every employee was skipped.');
        }

        return DB::transaction(function () use ($run, $user): PayrollRun {
            $run->forceFill([
                'status' => PayrollRun::STATUS_FINALIZED,
                'finalized_by' => $user->id,
                'finalized_at' => Carbon::now(),
            ])->save();

            $this->postToLedger($run, $user);

            return $run->refresh();
        });
    }

    /**
     * Delete a draft run outright. A draft never paid anyone, so there is
     * nothing to preserve — unlike a finalized run, which is never deleted.
     *
     * @throws PayrollRunStateException
     */
    public function discard(PayrollRun $run): void
    {
        $this->assertDraft($run);

        $run->delete();
    }

    /**
     * Record a correction against an already-finalized payslip. The payslip
     * row itself is never touched — see Payslip::adjustedNetPay for the
     * figure that reflects this.
     *
     * @throws PayrollRunStateException
     */
    public function addAdjustment(Payslip $payslip, float $amount, string $reason, User $user): PayslipAdjustment
    {
        if (! $payslip->payrollRun->isFinalized()) {
            throw new PayrollRunStateException('Adjustments can only be made against a finalized payroll run — recalculate the draft instead.');
        }

        // Not create(): 'created_by' is deliberately outside $fillable.
        $adjustment = new PayslipAdjustment([
            'amount' => $amount,
            'reason' => $reason,
        ]);
        $adjustment->payslip()->associate($payslip);
        $adjustment->created_by = $user->id;
        $adjustment->save();

        return $adjustment;
    }

    /**
     * Calculate and create one Payslip per eligible employee, recording
     * anyone skipped on the run itself.
     */
    private function populate(PayrollRun $run): void
    {
        $employees = Employee::query()
            ->employedDuring($run->period_start, $run->period_end)
            ->get();

        $skipped = [];

        foreach ($employees as $employee) {
            try {
                $result = $this->calculator->calculate($employee, $run->period_start->copy(), $run->period_end->copy());

                Payslip::create([
                    'payroll_run_id' => $run->id,
                    'employee_id' => $employee->id,
                    'basic_salary' => $result->basicSalary,
                    'total_days' => $result->totalDays,
                    'unpaid_days' => $result->unpaidDays,
                    'basic_after_attendance' => $result->basicAfterAttendance,
                    'allowance_items' => $result->allowanceItems,
                    'deduction_items' => $result->deductionItems,
                    'allowances_total' => $result->allowancesTotal,
                    'deductions_total' => $result->deductionsTotal,
                    'gross_pay' => $result->grossPay,
                    'pf_employee' => $result->pfEmployee,
                    'pf_employer' => $result->pfEmployer,
                    'ssf_employee' => $result->ssfEmployee,
                    'ssf_employer' => $result->ssfEmployer,
                    'taxable_income' => $result->taxableIncome,
                    'tds' => $result->tds,
                    'net_pay' => $result->netPay,
                ]);
            } catch (MissingSalaryStructureException|MissingPayrollRateException|MissingTaxSlabException $e) {
                $skipped[] = [
                    'employee_id' => $employee->id,
                    'name' => $employee->full_name,
                    'reason' => $e->getMessage(),
                ];
            }
        }

        $run->forceFill([
            'skipped_employees' => $skipped,
            'calculated_at' => Carbon::now(),
        ])->save();
    }

    /**
     * Post the run's aggregate payroll cost as one balanced journal entry:
     *
     *   Debit  Salary Expense     = net pay + PF/SSF/TDS withheld + employer PF/SSF
     *   Credit Salary Payable     = net pay owed to employees
     *   Credit Statutory Payable  = PF/SSF/TDS withheld + employer PF/SSF contributions
     *
     * One entry per run, not one per payslip — a payroll run is a single
     * financial event on the books, same granularity as one journal entry
     * per invoice. Employer PF/SSF contributions are recognised as their own
     * cost (on top of net pay) because they are money the company owes the
     * PF/SSF fund that was never part of any employee's pay; deductions
     * (loan repayments etc.) are already netted out of net_pay by
     * PayrollCalculationService, so they need no line of their own here —
     * they simply reduce what the company owes, not what it owes to a third
     * party.
     *
     * @throws MissingAccountingConfigurationException
     */
    private function postToLedger(PayrollRun $run, User $postedBy): void
    {
        $company = Company::current();

        $payslips = $run->payslips;
        $netPayTotal = round((float) $payslips->sum('net_pay'), 2);
        $statutoryTotal = round(
            (float) $payslips->sum('pf_employee') + (float) $payslips->sum('ssf_employee') + (float) $payslips->sum('tds')
            + (float) $payslips->sum('pf_employer') + (float) $payslips->sum('ssf_employer'),
            2
        );
        $expenseTotal = round($netPayTotal + $statutoryTotal, 2);
        $needsStatutoryAccount = $statutoryTotal > 0;

        if ($company->salary_expense_account_id === null
            || $company->salary_payable_account_id === null
            || ($needsStatutoryAccount && $company->statutory_payable_account_id === null)) {
            throw new MissingAccountingConfigurationException(
                'Configure the Salary Expense, Salary Payable and Statutory Payable accounts in Company Settings before finalizing payroll.'
            );
        }

        $period = "{$run->period_start->toDateString()} to {$run->period_end->toDateString()}";

        $lines = [
            ['account_id' => $company->salary_expense_account_id, 'debit' => $expenseTotal, 'credit' => 0, 'description' => "Payroll {$period}"],
            ['account_id' => $company->salary_payable_account_id, 'debit' => 0, 'credit' => $netPayTotal, 'description' => 'Net pay owed to employees'],
        ];

        if ($needsStatutoryAccount) {
            $lines[] = ['account_id' => $company->statutory_payable_account_id, 'debit' => 0, 'credit' => $statutoryTotal, 'description' => 'PF, SSF and TDS payable'];
        }

        $this->journalEntryService->post(
            entryDate: $run->period_end,
            description: "Payroll for {$period}",
            lines: $lines,
            postedBy: $postedBy,
            referenceType: PayrollRun::REFERENCE_TYPE,
            referenceId: $run->id,
        );
    }

    private function assertDraft(PayrollRun $run): void
    {
        if (! $run->isDraft()) {
            throw new PayrollRunStateException("This run is {$run->status}, not draft.");
        }
    }
}
