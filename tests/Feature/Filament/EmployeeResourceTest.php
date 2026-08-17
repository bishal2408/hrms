<?php

use App\Filament\Resources\Employees\Pages\CreateEmployee;
use App\Filament\Resources\Employees\Pages\EditEmployee;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
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

test('an admin can create an employee with BS dates, stored as AD', function () {
    $department = Department::factory()->create();
    $designation = Designation::factory()->create();

    Livewire::test(CreateEmployee::class)
        ->fillForm([
            'employee_code' => 'EMP-0001',
            'first_name' => 'Anita',
            'last_name' => 'Shrestha',
            'marital_status' => TaxSlab::MARITAL_SINGLE,
            'department_id' => $department->id,
            'designation_id' => $designation->id,
            'hired_at' => '2080-04-01',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $employee = Employee::sole();

    expect($employee->hired_at->toDateString())->toBe(NepaliCalendar::bsToAd('2080-04-01')->toDateString())
        ->and($employee->full_name)->toBe('Anita Shrestha')
        ->and($employee->is_active)->toBeTrue();
});

test('an employee can be created without a login account', function () {
    Livewire::test(CreateEmployee::class)
        ->fillForm([
            'employee_code' => 'EMP-0002',
            'first_name' => 'Ram',
            'last_name' => 'Thapa',
            'marital_status' => TaxSlab::MARITAL_MARRIED,
            'hired_at' => '2080-04-01',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Employee::sole()->user_id)->toBeNull();
});

test('editing an employee loads their stored values back into the form', function () {
    $employee = Employee::factory()->create([
        'first_name' => 'Sita',
        'last_name' => 'Gurung',
        'hired_at' => NepaliCalendar::bsToAd('2080-04-01')->toDateString(),
    ]);

    Livewire::test(EditEmployee::class, ['record' => $employee->getRouteKey()])
        ->assertOk()
        ->assertSchemaStateSet([
            'first_name' => 'Sita',
            'last_name' => 'Gurung',
            'hired_at' => '2080-04-01',
        ]);
});

test('terminating an employee ends employment without deleting the record', function () {
    $employee = Employee::factory()->create();

    Livewire::test(EditEmployee::class, ['record' => $employee->getRouteKey()])
        ->fillForm(['terminated_at' => '2081-04-01'])
        ->call('save')
        ->assertHasNoFormErrors();

    $employee->refresh();

    expect($employee->exists)->toBeTrue()
        ->and($employee->is_active)->toBeFalse()
        ->and($employee->terminated_at->toDateString())
        ->toBe(NepaliCalendar::bsToAd('2081-04-01')->toDateString());
});

test('an employee code cannot be reused', function () {
    Employee::factory()->create(['employee_code' => 'EMP-0001']);

    Livewire::test(CreateEmployee::class)
        ->fillForm([
            'employee_code' => 'EMP-0001',
            'first_name' => 'Duplicate',
            'last_name' => 'Person',
            'marital_status' => TaxSlab::MARITAL_SINGLE,
            'hired_at' => '2080-04-01',
        ])
        ->call('create')
        ->assertHasFormErrors(['employee_code']);
});
