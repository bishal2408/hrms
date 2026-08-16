<?php

use App\Filament\Resources\PayrollRates\Pages\ManagePayrollRates;
use App\Filament\Resources\TaxSlabs\Pages\ManageTaxSlabs;
use App\Models\PayrollRate;
use App\Models\TaxSlab;
use App\Models\User;
use App\Services\NepaliCalendar;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('super_admin', 'web');

    $this->admin = User::factory()->create()->assignRole('super_admin');
    $this->actingAs($this->admin);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

test('editing a tax slab loads its stored AD date back as BS', function () {
    $slab = TaxSlab::create([
        'marital_status' => TaxSlab::MARITAL_SINGLE,
        'lower_bound' => 0,
        'upper_bound' => 500000,
        'rate_percent' => 1,
        'effective_from' => NepaliCalendar::bsToAd('2080-04-01')->toDateString(),
    ]);

    Livewire::test(ManageTaxSlabs::class)
        ->mountAction(TestAction::make('edit')->table($slab))
        ->assertActionDataSet(['effective_from' => '2080-04-01']);
});

test('editing a payroll rate loads its stored AD date back as BS', function () {
    $rate = PayrollRate::create([
        'type' => PayrollRate::TYPE_PROVIDENT_FUND,
        'employee_contribution_percent' => 10,
        'employer_contribution_percent' => 10,
        'effective_from' => NepaliCalendar::bsToAd('2080-04-01')->toDateString(),
    ]);

    Livewire::test(ManagePayrollRates::class)
        ->mountAction(TestAction::make('edit')->table($rate))
        ->assertActionDataSet(['effective_from' => '2080-04-01']);
});

test('a BS date survives an edit that does not touch it', function () {
    $slab = TaxSlab::create([
        'marital_status' => TaxSlab::MARITAL_SINGLE,
        'lower_bound' => 0,
        'upper_bound' => 500000,
        'rate_percent' => 1,
        'effective_from' => NepaliCalendar::bsToAd('2080-04-01')->toDateString(),
    ]);

    Livewire::test(ManageTaxSlabs::class)
        ->callAction(TestAction::make('edit')->table($slab), data: [
            'rate_percent' => 5,
        ])
        ->assertHasNoActionErrors();

    // Re-saving must not shift the date by re-converting an already-BS value.
    expect($slab->refresh()->effective_from->toDateString())
        ->toBe(NepaliCalendar::bsToAd('2080-04-01')->toDateString())
        ->and((float) $slab->rate_percent)->toBe(5.0);
});
