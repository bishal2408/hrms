<?php

use App\Exports\PfSsfRemittanceExport;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('payroll_accountant', 'web');
    Role::findOrCreate('employee', 'web');
});

test('payroll_accountant can download the PF/SSF remittance report as an Excel file', function () {
    Excel::fake();

    $accountant = User::factory()->create()->assignRole('payroll_accountant');
    $run = PayrollRun::factory()->finalized()->create(['period_start' => '2026-07-01', 'period_end' => '2026-07-30']);
    Payslip::factory()->create(['payroll_run_id' => $run->id, 'pf_employee' => 3000]);

    $this->actingAs($accountant)
        ->get(route('pf-ssf-remittance.export', ['from' => '2026-07-01', 'until' => '2026-07-31']))
        ->assertOk();

    Excel::assertDownloaded('pf-ssf-remittance.xlsx', fn (PfSsfRemittanceExport $export): bool => $export->collection()->count() === 1);
});

test('a user without accounting access cannot download the PF/SSF remittance report', function () {
    $employee = User::factory()->create()->assignRole('employee');

    $this->actingAs($employee)
        ->get(route('pf-ssf-remittance.export'))
        ->assertForbidden();
});

test('an unauthenticated request is forbidden, not shown the file', function () {
    $this->get(route('pf-ssf-remittance.export'))->assertForbidden();
});

test('the real generated file contains the correct data and a totals row', function () {
    $accountant = User::factory()->create()->assignRole('payroll_accountant');
    $run = PayrollRun::factory()->finalized()->create(['period_start' => '2026-07-01', 'period_end' => '2026-07-30']);
    Payslip::factory()->create([
        'payroll_run_id' => $run->id,
        'pf_employee' => 3000, 'pf_employer' => 3000,
        'ssf_employee' => 3300, 'ssf_employer' => 6000,
    ]);

    $response = $this->actingAs($accountant)
        ->get(route('pf-ssf-remittance.export', ['from' => '2026-07-01', 'until' => '2026-07-31']))
        ->assertOk();

    $path = $response->getFile()->getPathname();
    $sheet = IOFactory::load($path)->getActiveSheet();

    // Row 1 = headings, row 2 = the payslip, row 3 = totals (1 data row +
    // header + totals).
    expect($sheet->getCell('A1')->getValue())->toBe('Period (BS)')
        ->and((float) $sheet->getCell('D2')->getValue())->toBe(3000.0)
        ->and($sheet->getCell('C3')->getValue())->toBe('Total')
        ->and((float) $sheet->getCell('D3')->getValue())->toBe(3000.0)
        ->and((float) $sheet->getCell('G3')->getValue())->toBe(6000.0);
});
