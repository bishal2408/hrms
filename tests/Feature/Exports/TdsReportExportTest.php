<?php

use App\Exports\TdsReportExport;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Services\PayrollComplianceReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $run = PayrollRun::factory()->finalized()->create(['period_start' => '2026-07-01', 'period_end' => '2026-07-30']);
    $employee = Employee::factory()->create(['first_name' => 'Amy', 'last_name' => 'Baker', 'pan_number' => '123456789']);
    Payslip::factory()->create([
        'payroll_run_id' => $run->id,
        'employee_id' => $employee->id,
        'taxable_income' => 42500,
        'tds' => 433.33,
    ]);

    $this->report = app(PayrollComplianceReportService::class)->tds();
});

test('headings match the on-screen report columns', function () {
    $export = new TdsReportExport($this->report);

    expect($export->headings())->toBe(['Period (BS)', 'Employee', 'PAN', 'Taxable income', 'TDS']);
});

test('map produces a row with the correct employee, PAN and figures', function () {
    $export = new TdsReportExport($this->report);
    $payslip = $this->report->payslips->sole();

    $row = $export->map($payslip);

    expect($row[1])->toBe('Amy Baker')
        ->and($row[2])->toBe('123456789')
        ->and($row[3])->toBe(42500.0)
        ->and($row[4])->toBe(433.33);
});
