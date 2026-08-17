<?php

use App\Filament\Resources\SalaryComponentTypes\Pages\ManageSalaryComponentTypes;
use App\Models\SalaryComponentType;
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

test('an admin can create a salary component', function () {
    Livewire::test(ManageSalaryComponentTypes::class)
        ->callAction('create', data: [
            'name' => 'Transport Allowance',
            'code' => 'transport',
            'component_type' => SalaryComponentType::TYPE_ALLOWANCE,
            'is_active' => true,
        ])
        ->assertHasNoActionErrors();

    expect(SalaryComponentType::where('code', 'transport')->exists())->toBeTrue();
});

test('editing a component loads its stored values', function () {
    $component = SalaryComponentType::factory()->create(['name' => 'Transport Allowance', 'code' => 'transport']);

    Livewire::test(ManageSalaryComponentTypes::class)
        ->mountAction(TestAction::make('edit')->table($component))
        ->assertActionDataSet(['name' => 'Transport Allowance', 'code' => 'transport']);
});

test('a component code cannot be reused', function () {
    SalaryComponentType::factory()->create(['code' => 'transport']);

    Livewire::test(ManageSalaryComponentTypes::class)
        ->callAction('create', data: [
            'name' => 'Transport Allowance Two',
            'code' => 'transport',
            'component_type' => SalaryComponentType::TYPE_ALLOWANCE,
        ])
        ->assertHasActionErrors(['code']);
});
