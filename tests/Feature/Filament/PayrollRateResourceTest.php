<?php

use App\Filament\Resources\PayrollRates\Pages\ManagePayrollRates;
use App\Models\PayrollRate;
use App\Models\User;
use App\Services\NepaliCalendar;
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

test('an admin can create a payroll rate with a BS effective date, stored as AD', function () {
    Livewire::test(ManagePayrollRates::class)
        ->callAction('create', data: [
            'type' => PayrollRate::TYPE_PROVIDENT_FUND,
            'employee_contribution_percent' => 10,
            'employer_contribution_percent' => 10,
            'effective_from' => '2080-04-01',
        ])
        ->assertHasNoActionErrors();

    $rate = PayrollRate::sole();

    expect($rate->effective_from->toDateString())->toBe(NepaliCalendar::bsToAd('2080-04-01')->toDateString());
});
