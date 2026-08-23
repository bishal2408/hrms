<?php

use App\Filament\Pages\TdsReportPage;
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

test('payroll_accountant can access the TDS report page', function () {
    $accountant = User::factory()->create()->assignRole('payroll_accountant');
    $this->actingAs($accountant);

    expect(TdsReportPage::canAccess())->toBeTrue();
});

test('a manager cannot access the TDS report page', function () {
    $manager = User::factory()->create()->assignRole('manager');
    $this->actingAs($manager);

    expect(TdsReportPage::canAccess())->toBeFalse();
});

test('the page shows correct totals for finalized payslips', function () {
    $accountant = User::factory()->create()->assignRole('payroll_accountant');
    $this->actingAs($accountant);

    $run = PayrollRun::factory()->finalized()->create(['period_start' => '2026-07-01', 'period_end' => '2026-07-30']);
    Payslip::factory()->create(['payroll_run_id' => $run->id, 'taxable_income' => 42500, 'tds' => 433.33]);

    $report = Livewire::test(TdsReportPage::class)->instance()->report;

    expect($report->totalTaxableIncome)->toBe(42500.0)
        ->and($report->totalTds)->toBe(433.33);
});
