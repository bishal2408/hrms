<?php

use App\Filament\Resources\TaxSlabs\Pages\ManageTaxSlabs;
use App\Models\TaxSlab;
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

test('an admin can create a tax slab with a BS effective date, stored as AD', function () {
    Livewire::test(ManageTaxSlabs::class)
        ->callAction('create', data: [
            'marital_status' => TaxSlab::MARITAL_SINGLE,
            'lower_bound' => 0,
            'upper_bound' => 500000,
            'rate_percent' => 1,
            'effective_from' => '2080-04-01',
        ])
        ->assertHasNoActionErrors();

    $slab = TaxSlab::sole();

    expect($slab->effective_from->toDateString())->toBe(NepaliCalendar::bsToAd('2080-04-01')->toDateString());
});
