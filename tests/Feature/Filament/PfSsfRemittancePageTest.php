<?php

use App\Filament\Pages\PfSsfRemittancePage;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('payroll_accountant', 'web');
    Role::findOrCreate('manager', 'web');

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

test('payroll_accountant can access the PF/SSF remittance page', function () {
    $accountant = User::factory()->create()->assignRole('payroll_accountant');
    $this->actingAs($accountant);

    expect(PfSsfRemittancePage::canAccess())->toBeTrue();
});

test('a manager cannot access the PF/SSF remittance page', function () {
    $manager = User::factory()->create()->assignRole('manager');
    $this->actingAs($manager);

    expect(PfSsfRemittancePage::canAccess())->toBeFalse();
});

test('the page shows correct totals for finalized payslips', function () {
    $accountant = User::factory()->create()->assignRole('payroll_accountant');
    $this->actingAs($accountant);

    $run = PayrollRun::factory()->finalized()->create(['period_start' => '2026-07-01', 'period_end' => '2026-07-30']);
    Payslip::factory()->create([
        'payroll_run_id' => $run->id,
        'pf_employee' => 3000, 'pf_employer' => 3000,
        'ssf_employee' => 3300, 'ssf_employer' => 6000,
    ]);

    $report = Livewire::test(PfSsfRemittancePage::class)->instance()->report;

    expect($report->totalPfEmployee)->toBe(3000.0)
        ->and($report->totalPfEmployer)->toBe(3000.0)
        ->and($report->grandTotal())->toBe(15300.0);
});
