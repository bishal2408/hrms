<?php

use App\Exceptions\MissingAccountingConfigurationException;
use App\Exceptions\PayrollRunStateException;
use App\Models\Account;
use App\Models\Company;
use App\Models\Employee;
use App\Models\PayrollRate;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\SalaryComponentType;
use App\Models\SalaryStructure;
use App\Models\SalaryStructureItem;
use App\Models\TaxSlab;
use App\Models\User;
use App\Services\JournalEntryService;
use App\Services\PayrollCalculationService;
use App\Services\PayrollRunService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new PayrollRunService(new PayrollCalculationService, new JournalEntryService);
    $this->creator = User::factory()->create();

    $this->periodStart = Carbon::parse('2026-07-01');
    $this->periodEnd = Carbon::parse('2026-07-30');

    PayrollRate::create(['type' => PayrollRate::TYPE_PROVIDENT_FUND, 'employee_contribution_percent' => 10, 'employer_contribution_percent' => 10, 'effective_from' => '2020-01-01']);
    PayrollRate::create(['type' => PayrollRate::TYPE_SOCIAL_SECURITY_FUND, 'employee_contribution_percent' => 11, 'employer_contribution_percent' => 20, 'effective_from' => '2020-01-01']);
    foreach ([TaxSlab::MARITAL_SINGLE, TaxSlab::MARITAL_MARRIED] as $status) {
        TaxSlab::create(['marital_status' => $status, 'lower_bound' => 0, 'upper_bound' => 500000, 'rate_percent' => 1, 'effective_from' => '2020-01-01']);
        TaxSlab::create(['marital_status' => $status, 'lower_bound' => 500000, 'upper_bound' => null, 'rate_percent' => 2, 'effective_from' => '2020-01-01']);
    }

    $this->salaryExpense = Account::factory()->expense()->create(['code' => '5100', 'name' => 'Salary Expense']);
    $this->salaryPayable = Account::factory()->liability()->create(['code' => '2200', 'name' => 'Salary Payable']);
    $this->statutoryPayable = Account::factory()->liability()->create(['code' => '2300', 'name' => 'Statutory Payable']);

    Company::create([
        'name' => 'Test Co',
        'salary_expense_account_id' => $this->salaryExpense->id,
        'salary_payable_account_id' => $this->salaryPayable->id,
        'statutory_payable_account_id' => $this->statutoryPayable->id,
    ]);
});

/**
 * An employee with a salary structure, eligible for payroll, with a full
 * AttendanceLog for the file's fixed period (2026-07-01 to 2026-07-30) —
 * PayrollCalculationService pays only days with an AttendanceLog row or a
 * paid approved leave (see its class docblock), so without this every
 * payslip nets to $0 and finalize()'s ledger posting has nothing to post.
 *
 * hired_at defaults safely before every period this file uses — the
 * factory's own random default (anywhere in the last 5 years) is not
 * guaranteed to overlap a fixed test period, which would make
 * employedDuring() exclude the employee for the wrong reason and produce an
 * intermittently failing test. Callers testing hire/termination boundaries
 * override it explicitly.
 */
function payableEmployee(array $employeeAttrs = [], float $basicSalary = 30000): Employee
{
    $employee = Employee::factory()->create(['hired_at' => '2020-01-01', ...$employeeAttrs]);
    SalaryStructure::create(['employee_id' => $employee->id, 'basic_salary' => $basicSalary, 'effective_from' => '2020-01-01']);
    fullMonthAttendance($employee);

    return $employee;
}

test('running payroll creates a draft run with a payslip per eligible employee', function () {
    payableEmployee();
    payableEmployee();

    $run = $this->service->run($this->periodStart, $this->periodEnd, $this->creator);

    expect($run->status)->toBe(PayrollRun::STATUS_DRAFT)
        ->and($run->created_by)->toBe($this->creator->id)
        ->and($run->calculated_at)->not->toBeNull()
        ->and($run->payslips)->toHaveCount(2)
        ->and($run->skipped_employees)->toBe([]);
});

test('an employee missing a salary structure is skipped, not fatal to the whole run', function () {
    payableEmployee();
    $unconfigured = Employee::factory()->create(['hired_at' => '2020-01-01']); // no SalaryStructure at all

    $run = $this->service->run($this->periodStart, $this->periodEnd, $this->creator);

    expect($run->payslips)->toHaveCount(1)
        ->and($run->skipped_employees)->toHaveCount(1)
        ->and($run->skipped_employees[0]['employee_id'])->toBe($unconfigured->id);
});

test('the payslip freezes the itemized allowance and deduction lines', function () {
    $employee = payableEmployee();
    $structure = $employee->salaryStructures()->sole();
    $transport = SalaryComponentType::factory()->create(['name' => 'Transport Allowance']);
    SalaryStructureItem::create(['salary_structure_id' => $structure->id, 'salary_component_type_id' => $transport->id, 'amount' => 3000]);

    $run = $this->service->run($this->periodStart, $this->periodEnd, $this->creator);
    $payslip = $run->payslips()->sole();

    // Not a strict array match: a whole-number float loses its trailing .0
    // through the JSON round-trip (json_encode(3000.0) === "3000"), so it
    // decodes back as an int. Harmless — every real usage formats the amount
    // explicitly — but not something a strict === comparison should assume.
    expect($payslip->allowance_items)->toHaveCount(1)
        ->and($payslip->allowance_items[0]['name'])->toBe('Transport Allowance')
        ->and((float) $payslip->allowance_items[0]['amount'])->toBe(3000.0);
});

test('running the exact same period twice is rejected', function () {
    payableEmployee();
    $this->service->run($this->periodStart, $this->periodEnd, $this->creator);

    $this->service->run($this->periodStart, $this->periodEnd, $this->creator);
})->throws(PayrollRunStateException::class);

test('an employee hired after the period is not paid for it', function () {
    payableEmployee(['hired_at' => '2026-08-15']); // after periodEnd

    $run = $this->service->run($this->periodStart, $this->periodEnd, $this->creator);

    expect($run->payslips)->toHaveCount(0);
});

test('an employee terminated mid-period is still paid for the days they worked', function () {
    payableEmployee(['hired_at' => '2020-01-01', 'terminated_at' => '2026-07-15']);

    $run = $this->service->run($this->periodStart, $this->periodEnd, $this->creator);

    expect($run->payslips)->toHaveCount(1);
});

test('an employee terminated before the period started is not included', function () {
    payableEmployee(['hired_at' => '2020-01-01', 'terminated_at' => '2026-06-01']);

    $run = $this->service->run($this->periodStart, $this->periodEnd, $this->creator);

    expect($run->payslips)->toHaveCount(0);
});

test('recalculating a draft replaces its payslips with fresh numbers', function () {
    $employee = payableEmployee(basicSalary: 30000);
    $run = $this->service->run($this->periodStart, $this->periodEnd, $this->creator);
    $originalPayslipId = $run->payslips()->sole()->id;

    // Pay changes before finalization — a new structure supersedes the old.
    SalaryStructure::create(['employee_id' => $employee->id, 'basic_salary' => 45000, 'effective_from' => '2026-07-01']);

    $run = $this->service->recalculate($run);
    $payslip = $run->payslips()->sole();

    expect($payslip->id)->not->toBe($originalPayslipId)
        ->and((float) $payslip->basic_salary)->toBe(45000.0);
});

test('a finalized run cannot be recalculated or discarded', function () {
    payableEmployee();
    $run = $this->service->run($this->periodStart, $this->periodEnd, $this->creator);
    $this->service->finalize($run, $this->creator);

    $this->service->recalculate($run);
})->throws(PayrollRunStateException::class);

test('a finalized run cannot be discarded either', function () {
    payableEmployee();
    $run = $this->service->run($this->periodStart, $this->periodEnd, $this->creator);
    $this->service->finalize($run, $this->creator);

    $this->service->discard($run);
})->throws(PayrollRunStateException::class);

test('finalizing sets who finalized it and when', function () {
    payableEmployee();
    $run = $this->service->run($this->periodStart, $this->periodEnd, $this->creator);

    $run = $this->service->finalize($run, $this->creator);

    expect($run->status)->toBe(PayrollRun::STATUS_FINALIZED)
        ->and($run->finalized_by)->toBe($this->creator->id)
        ->and($run->finalized_at)->not->toBeNull();
});

test('a run with no successful payslips cannot be finalized', function () {
    Employee::factory()->create(['hired_at' => '2020-01-01']); // no salary structure — will be skipped
    $run = $this->service->run($this->periodStart, $this->periodEnd, $this->creator);

    $this->service->finalize($run, $this->creator);
})->throws(PayrollRunStateException::class);

test('discarding a draft removes it and its payslips', function () {
    payableEmployee();
    $run = $this->service->run($this->periodStart, $this->periodEnd, $this->creator);
    $runId = $run->id;

    $this->service->discard($run);

    expect(PayrollRun::find($runId))->toBeNull();
});

test('a period can be re-run after its draft is discarded', function () {
    // Regression test for a real bug: PayrollRun used SoftDeletes, but its
    // unique index on (period_start, period_end) has no concept of
    // deleted_at, so a discarded (soft-deleted) run kept blocking its period
    // forever with a raw SQL 1062 duplicate-key error. PayrollRun no longer
    // soft-deletes (see its class docblock) — this proves the period is
    // genuinely free again and no orphaned payslip rows are left behind.
    payableEmployee();
    $run = $this->service->run($this->periodStart, $this->periodEnd, $this->creator);
    $this->service->discard($run);

    $rerun = $this->service->run($this->periodStart, $this->periodEnd, $this->creator);

    expect($rerun->id)->not->toBeNull()
        ->and($rerun->payslips)->toHaveCount(1)
        ->and(Payslip::count())->toBe(1);
});

test('an adjustment can only be made against a finalized payslip', function () {
    payableEmployee();
    $run = $this->service->run($this->periodStart, $this->periodEnd, $this->creator);
    $payslip = $run->payslips()->sole();

    $this->service->addAdjustment($payslip, 500, 'Missed allowance', $this->creator);
})->throws(PayrollRunStateException::class);

test('an adjustment changes the adjusted net pay without touching the frozen payslip figures', function () {
    payableEmployee();
    $run = $this->service->run($this->periodStart, $this->periodEnd, $this->creator);
    $run = $this->service->finalize($run, $this->creator);
    $payslip = $run->payslips()->sole();
    $originalNetPay = (float) $payslip->net_pay;

    $this->service->addAdjustment($payslip, 500, 'Missed allowance', $this->creator);
    $this->service->addAdjustment($payslip, -100, 'Overpaid transport last month', $this->creator);

    $payslip->refresh();

    expect((float) $payslip->net_pay)->toBe($originalNetPay) // frozen, untouched
        ->and($payslip->adjusted_net_pay)->toBe(round($originalNetPay + 500 - 100, 2))
        ->and($payslip->adjustments)->toHaveCount(2);
});

test('finalizing posts a balanced journal entry: debit salary expense, credit salary payable and statutory payable', function () {
    payableEmployee(basicSalary: 30000);
    $run = $this->service->run($this->periodStart, $this->periodEnd, $this->creator);
    $payslip = $run->payslips()->sole();

    $run = $this->service->finalize($run, $this->creator);

    $entry = $run->journalEntry();
    expect($entry)->not->toBeNull()
        ->and($entry->lines)->toHaveCount(3)
        ->and((float) $entry->lines->sum('debit'))->toBe((float) $entry->lines->sum('credit'));

    $statutoryTotal = round(
        (float) $payslip->pf_employee + (float) $payslip->ssf_employee + (float) $payslip->tds
        + (float) $payslip->pf_employer + (float) $payslip->ssf_employer,
        2
    );
    $expenseTotal = round((float) $payslip->net_pay + $statutoryTotal, 2);

    $expenseLine = $entry->lines->firstWhere('account_id', $this->salaryExpense->id);
    $payableLine = $entry->lines->firstWhere('account_id', $this->salaryPayable->id);
    $statutoryLine = $entry->lines->firstWhere('account_id', $this->statutoryPayable->id);

    expect((float) $expenseLine->debit)->toBe($expenseTotal)
        ->and((float) $payableLine->credit)->toBe((float) $payslip->net_pay)
        ->and((float) $statutoryLine->credit)->toBe($statutoryTotal);
});

test('finalizing a multi-employee run posts one entry aggregating every payslip', function () {
    payableEmployee(basicSalary: 30000);
    payableEmployee(basicSalary: 45000);
    $run = $this->service->run($this->periodStart, $this->periodEnd, $this->creator);
    $expectedNetPay = round((float) $run->payslips->sum('net_pay'), 2);

    $run = $this->service->finalize($run, $this->creator);

    $entry = $run->journalEntry();
    $payableLine = $entry->lines->firstWhere('account_id', $this->salaryPayable->id);

    expect((float) $payableLine->credit)->toBe($expectedNetPay);
});

test('finalizing without payroll accounting configured is rejected', function () {
    Company::current()->update(['salary_expense_account_id' => null]);
    payableEmployee();
    $run = $this->service->run($this->periodStart, $this->periodEnd, $this->creator);

    $this->service->finalize($run, $this->creator);
})->throws(MissingAccountingConfigurationException::class);

test('a rejected finalize (missing accounting configuration) leaves the run a draft', function () {
    Company::current()->update(['salary_expense_account_id' => null]);
    payableEmployee();
    $run = $this->service->run($this->periodStart, $this->periodEnd, $this->creator);

    try {
        $this->service->finalize($run, $this->creator);
    } catch (MissingAccountingConfigurationException) {
        // expected
    }

    expect($run->fresh()->isDraft())->toBeTrue();
});
