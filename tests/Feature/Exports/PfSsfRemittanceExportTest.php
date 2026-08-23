<?php

use App\Exports\PfSsfRemittanceExport;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Services\PayrollComplianceReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $run = PayrollRun::factory()->finalized()->create(['period_start' => '2026-07-01', 'period_end' => '2026-07-30']);
    $employee = Employee::factory()->create(['first_name' => 'Amy', 'last_name' => 'Baker', 'employee_code' => 'EMP-001']);
    Payslip::factory()->create([
        'payroll_run_id' => $run->id,
        'employee_id' => $employee->id,
        'pf_employee' => 3000, 'pf_employer' => 3000,
        'ssf_employee' => 3300, 'ssf_employer' => 6000,
    ]);

    $this->report = app(PayrollComplianceReportService::class)->pfSsfRemittance();
});

test('headings match the on-screen report columns', function () {
    $export = new PfSsfRemittanceExport($this->report);

    expect($export->headings())->toBe(['Period (BS)', 'Employee', 'Code', 'PF (employee)', 'PF (employer)', 'SSF (employee)', 'SSF (employer)']);
});

test('map produces a row with the correct employee and contribution figures', function () {
    $export = new PfSsfRemittanceExport($this->report);
    $payslip = $this->report->payslips->sole();

    $row = $export->map($payslip);

    expect($row[1])->toBe('Amy Baker')
        ->and($row[2])->toBe('EMP-001')
        ->and($row[3])->toBe(3000.0)
        ->and($row[4])->toBe(3000.0)
        ->and($row[5])->toBe(3300.0)
        ->and($row[6])->toBe(6000.0);
});
