<?php

use App\Filament\Widgets\PayrollTrendChart;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('payroll_accountant', 'web')->givePermissionTo(
        Permission::findOrCreate('ViewAny:PayrollRun', 'web'),
    );
    Role::findOrCreate('employee', 'web');
});

test('canView requires ViewAny:PayrollRun', function () {
    $accountant = User::factory()->create()->assignRole('payroll_accountant');
    $this->actingAs($accountant);
    expect(PayrollTrendChart::canView())->toBeTrue();

    $employee = User::factory()->create()->assignRole('employee');
    $this->actingAs($employee);
    expect(PayrollTrendChart::canView())->toBeFalse();
});

test('only finalized runs appear, a draft is excluded', function () {
    $accountant = User::factory()->create()->assignRole('payroll_accountant');
    $this->actingAs($accountant);

    $finalized = PayrollRun::factory()->finalized()->create(['period_start' => '2026-06-01', 'period_end' => '2026-06-30']);
    Payslip::factory()->create(['payroll_run_id' => $finalized->id, 'net_pay' => 25000]);

    PayrollRun::factory()->create(['period_start' => '2026-07-01', 'period_end' => '2026-07-30']); // draft — excluded

    $data = chartData(new PayrollTrendChart);

    expect($data['labels'])->toHaveCount(1)
        ->and($data['labels'][0])->toBe('Jun 2026');
});

test('net pay per run is summed from its payslips, in chronological order', function () {
    $accountant = User::factory()->create()->assignRole('payroll_accountant');
    $this->actingAs($accountant);

    $june = PayrollRun::factory()->finalized()->create(['period_start' => '2026-06-01', 'period_end' => '2026-06-30']);
    Payslip::factory()->create(['payroll_run_id' => $june->id, 'net_pay' => 20000]);
    Payslip::factory()->create(['payroll_run_id' => $june->id, 'net_pay' => 15000]);

    $july = PayrollRun::factory()->finalized()->create(['period_start' => '2026-07-01', 'period_end' => '2026-07-30']);
    Payslip::factory()->create(['payroll_run_id' => $july->id, 'net_pay' => 40000]);

    $data = chartData(new PayrollTrendChart);

    expect($data['labels'])->toBe(['Jun 2026', 'Jul 2026'])
        ->and($data['datasets'][0]['data'])->toBe([35000.0, 40000.0]);
});

test('a run with no payslips at all shows as 0, not a missing entry', function () {
    $accountant = User::factory()->create()->assignRole('payroll_accountant');
    $this->actingAs($accountant);

    // Finalizing with no payslips can't happen via PayrollRunService (it
    // rejects a run with none), but this proves the chart itself won't
    // silently drop a run with an unexpected zero.
    PayrollRun::factory()->finalized()->create(['period_start' => '2026-06-01', 'period_end' => '2026-06-30']);

    $data = chartData(new PayrollTrendChart);

    expect($data['datasets'][0]['data'])->toBe([0.0]);
});

test('only the latest 6 finalized runs appear', function () {
    $accountant = User::factory()->create()->assignRole('payroll_accountant');
    $this->actingAs($accountant);

    foreach (range(1, 8) as $month) {
        $run = PayrollRun::factory()->finalized()->create([
            'period_start' => sprintf('2026-%02d-01', $month),
            'period_end' => sprintf('2026-%02d-28', $month),
        ]);
        Payslip::factory()->create(['payroll_run_id' => $run->id, 'net_pay' => 10000]);
    }

    $data = chartData(new PayrollTrendChart);

    // Months 3-8 — the 6 most recent, oldest to newest, not 1-6.
    expect($data['labels'])->toBe(['Mar 2026', 'Apr 2026', 'May 2026', 'Jun 2026', 'Jul 2026', 'Aug 2026']);
});
