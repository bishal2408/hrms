<?php

use App\Filament\Resources\Customers\Pages\ManageCustomers;
use App\Models\Customer;
use App\Models\User;
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

test('an admin can create a customer', function () {
    Livewire::test(ManageCustomers::class)
        ->callAction('create', data: [
            'name' => 'Acme Traders',
            'pan_number' => '123456789',
            'is_active' => true,
        ])
        ->assertHasNoActionErrors();

    expect(Customer::where('name', 'Acme Traders')->exists())->toBeTrue();
});

test('editing a customer loads its stored values', function () {
    $customer = Customer::factory()->create(['name' => 'Acme Traders']);

    Livewire::test(ManageCustomers::class)
        ->mountAction(TestAction::make('edit')->table($customer))
        ->assertActionDataSet(['name' => 'Acme Traders']);
});
