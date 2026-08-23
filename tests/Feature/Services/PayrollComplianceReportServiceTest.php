<?php

use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Services\PayrollComplianceReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new PayrollComplianceReportService;
});

test('pfSsfRemittance sums PF and SSF employee/employer contributions across the period', function () {
    $run = PayrollRun::factory()->finalized()->create(['period_start' => '2026-07-01', 'period_end' => '2026-07-30']);
    Payslip::factory()->create(['payroll_run_id' => $run->id, 'pf_employee' => 3000, 'pf_employer' => 3000, 'ssf_employee' => 3300, 'ssf_employer' => 6000]);
    Payslip::factory()->create(['payroll_run_id' => $run->id, 'pf_employee' => 1000, 'pf_employer' => 1000, 'ssf_employee' => 1100, 'ssf_employer' => 2000]);

    $report = $this->service->pfSsfRemittance(Carbon::parse('2026-07-01'), Carbon::parse('2026-07-30'));

    expect($report->payslips)->toHaveCount(2)
        ->and($report->totalPfEmployee)->toBe(4000.0)
        ->and($report->totalPfEmployer)->toBe(4000.0)
        ->and($report->totalSsfEmployee)->toBe(4400.0)
        ->and($report->totalSsfEmployer)->toBe(8000.0)
        ->and($report->totalPf())->toBe(8000.0)
        ->and($report->totalSsf())->toBe(12400.0)
        ->and($report->grandTotal())->toBe(20400.0);
});

test('pfSsfRemittance excludes a draft run\'s payslips', function () {
    $draft = PayrollRun::factory()->create(['period_start' => '2026-07-01', 'period_end' => '2026-07-30']); // still draft
    Payslip::factory()->create(['payroll_run_id' => $draft->id, 'pf_employee' => 5000]);

    $report = $this->service->pfSsfRemittance();

    expect($report->payslips)->toHaveCount(0)
        ->and($report->totalPfEmployee)->toBe(0.0);
});

test('pfSsfRemittance excludes runs outside the date range, including on the boundary date', function () {
    $inRange = PayrollRun::factory()->finalized()->create(['period_start' => '2026-07-01', 'period_end' => '2026-07-30']);
    Payslip::factory()->create(['payroll_run_id' => $inRange->id, 'pf_employee' => 1000]);

    $outOfRange = PayrollRun::factory()->finalized()->create(['period_start' => '2026-08-01', 'period_end' => '2026-08-31']);
    Payslip::factory()->create(['payroll_run_id' => $outOfRange->id, 'pf_employee' => 9000]);

    $report = $this->service->pfSsfRemittance(Carbon::parse('2026-07-01'), Carbon::parse('2026-07-30'));

    expect($report->payslips)->toHaveCount(1)
        ->and($report->totalPfEmployee)->toBe(1000.0);
});

test('pfSsfRemittance is empty with zero totals when there are no finalized payslips at all', function () {
    $report = $this->service->pfSsfRemittance();

    expect($report->payslips)->toHaveCount(0)
        ->and($report->totalPfEmployee)->toBe(0.0)
        ->and($report->totalPfEmployer)->toBe(0.0)
        ->and($report->totalSsfEmployee)->toBe(0.0)
        ->and($report->totalSsfEmployer)->toBe(0.0)
        ->and($report->grandTotal())->toBe(0.0);
});

test('tds sums taxable income and TDS withheld across the period', function () {
    $run = PayrollRun::factory()->finalized()->create(['period_start' => '2026-07-01', 'period_end' => '2026-07-30']);
    Payslip::factory()->create(['payroll_run_id' => $run->id, 'taxable_income' => 42500, 'tds' => 433.33]);
    Payslip::factory()->create(['payroll_run_id' => $run->id, 'taxable_income' => 10000, 'tds' => 0]);

    $report = $this->service->tds(Carbon::parse('2026-07-01'), Carbon::parse('2026-07-30'));

    expect($report->payslips)->toHaveCount(2)
        ->and($report->totalTaxableIncome)->toBe(52500.0)
        ->and($report->totalTds)->toBe(433.33);
});

test('tds excludes a draft run\'s payslips', function () {
    $draft = PayrollRun::factory()->create();
    Payslip::factory()->create(['payroll_run_id' => $draft->id, 'tds' => 500]);

    $report = $this->service->tds();

    expect($report->payslips)->toHaveCount(0)
        ->and($report->totalTds)->toBe(0.0);
});

test('lines are sorted by period then employee name', function () {
    $july = PayrollRun::factory()->finalized()->create(['period_start' => '2026-07-01', 'period_end' => '2026-07-30']);
    $june = PayrollRun::factory()->finalized()->create(['period_start' => '2026-06-01', 'period_end' => '2026-06-30']);

    $zoe = Employee::factory()->create(['first_name' => 'Zoe', 'last_name' => 'Adams']);
    $amy = Employee::factory()->create(['first_name' => 'Amy', 'last_name' => 'Baker']);

    Payslip::factory()->create(['payroll_run_id' => $july->id, 'employee_id' => $zoe->id]);
    Payslip::factory()->create(['payroll_run_id' => $june->id, 'employee_id' => $zoe->id]);
    Payslip::factory()->create(['payroll_run_id' => $june->id, 'employee_id' => $amy->id]);

    $report = $this->service->pfSsfRemittance();

    expect($report->payslips->pluck('employee.full_name')->all())->toBe(['Amy Baker', 'Zoe Adams', 'Zoe Adams'])
        ->and($report->payslips->pluck('payroll_run_id')->all())->toBe([$june->id, $june->id, $july->id]);
});
