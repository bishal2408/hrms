<?php

use App\Filament\Resources\VatRates\Pages\ManageVatRates;
use App\Models\User;
use App\Models\VatRate;
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

test('an admin can create a VAT rate with a BS effective date, stored as AD', function () {
    Livewire::test(ManageVatRates::class)
        ->callAction('create', data: [
            'rate_percent' => 13,
            'effective_from' => '2080-04-01',
        ])
        ->assertHasNoActionErrors();

    $rate = VatRate::sole();

    expect((float) $rate->rate_percent)->toBe(13.0)
        ->and($rate->effective_from->toDateString())->toBe(NepaliCalendar::bsToAd('2080-04-01')->toDateString());
});
