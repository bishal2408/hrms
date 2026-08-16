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

test('a stored AD date is shown in BS, with the AD value kept underneath', function () {
    $ad = NepaliCalendar::bsToAd('2080-04-01');

    PayrollRate::create([
        'type' => PayrollRate::TYPE_PROVIDENT_FUND,
        'employee_contribution_percent' => 10,
        'employer_contribution_percent' => 10,
        'effective_from' => $ad->toDateString(),
    ]);

    Livewire::test(ManagePayrollRates::class)
        ->assertOk()
        // BS is what Nepali users read...
        ->assertSee('2080-04-01')
        // ...and the AD value stays visible for auditors and exports (DESIGN.md B2).
        ->assertSee($ad->toDateString());
});
