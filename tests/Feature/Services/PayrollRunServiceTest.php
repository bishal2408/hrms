<?php

use App\Exceptions\PayrollRunStateException;
use App\Models\Employee;
use App\Models\PayrollRate;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\SalaryComponentType;
use App\Models\SalaryStructure;
use App\Models\SalaryStructureItem;
use App\Models\TaxSlab;
use App\Models\User;
use App\Services\PayrollCalculationService;
use App\Services\PayrollRunService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new PayrollRunService(new PayrollCalculationService);
    $this->creator = User::factory()->create();

    $this->periodStart = Carbon::parse('2026-07-01');
    $this->periodEnd = Carbon::parse('2026-07-30');

    PayrollRate::create(['type' => PayrollRate::TYPE_PROVIDENT_FUND, 'employee_contribution_percent' => 10, 'employer_contribution_percent' => 10, 'effective_from' => '2020-01-01']);
    PayrollRate::create(['type' => PayrollRate::TYPE_SOCIAL_SECURITY_FUND, 'employee_contribution_percent' => 11, 'employer_contribution_percent' => 20, 'effective_from' => '2020-01-01']);
    foreach ([TaxSlab::MARITAL_SINGLE, TaxSlab::MARITAL_MARRIED] as $status) {
        TaxSlab::create(['marital_status' => $status, 'lower_bound' => 0, 'upper_bound' => 500000, 'rate_percent' => 1, 'effective_from' => '2020-01-01']);
        TaxSlab::create(['marital_status' => $status, 'lower_bound' => 500000, 'upper_bound' => null, 'rate_percent' => 2, 'effective_from' => '2020-01-01']);
    }
});

/**
 * An employee with a salary structure, eligible for payroll.
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
