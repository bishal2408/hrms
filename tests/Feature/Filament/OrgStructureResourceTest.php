<?php

use App\Filament\Resources\Departments\Pages\ManageDepartments;
use App\Filament\Resources\Designations\Pages\ManageDesignations;
use App\Models\Department;
use App\Models\Designation;
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

test('an admin can create a department', function () {
    Livewire::test(ManageDepartments::class)
        ->callAction('create', data: [
            'name' => 'Finance',
            'code' => 'FIN',
            'is_active' => true,
        ])
        ->assertHasNoActionErrors();

    expect(Department::where('code', 'FIN')->exists())->toBeTrue();
});

test('editing a department loads its stored values', function () {
    $department = Department::factory()->create(['name' => 'Finance', 'code' => 'FIN']);

    Livewire::test(ManageDepartments::class)
        ->mountAction(TestAction::make('edit')->table($department))
        ->assertActionDataSet(['name' => 'Finance', 'code' => 'FIN']);
});

test('a department code cannot be reused', function () {
    Department::factory()->create(['code' => 'FIN']);

    Livewire::test(ManageDepartments::class)
        ->callAction('create', data: [
            'name' => 'Finance Two',
            'code' => 'FIN',
        ])
        ->assertHasActionErrors(['code']);
});

test('an admin can create a designation', function () {
    Livewire::test(ManageDesignations::class)
        ->callAction('create', data: [
            'name' => 'Senior Accountant',
            'is_active' => true,
        ])
        ->assertHasNoActionErrors();

    expect(Designation::where('name', 'Senior Accountant')->exists())->toBeTrue();
});

test('editing a designation loads its stored values', function () {
    $designation = Designation::factory()->create(['name' => 'Senior Accountant']);

    Livewire::test(ManageDesignations::class)
        ->mountAction(TestAction::make('edit')->table($designation))
        ->assertActionDataSet(['name' => 'Senior Accountant']);
});
