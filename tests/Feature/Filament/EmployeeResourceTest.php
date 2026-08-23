<?php

use App\Filament\Resources\Employees\Pages\CreateEmployee;
use App\Filament\Resources\Employees\Pages\EditEmployee;
use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\SalaryStructure;
use App\Models\TaxSlab;
use App\Models\User;
use App\Services\NepaliCalendar;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
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

test('a user who can view salary structures sees the employee\'s current salary in the list', function () {
    Role::findOrCreate('payroll_accountant', 'web')->givePermissionTo(
        Permission::findOrCreate('ViewAny:Employee', 'web'),
        Permission::findOrCreate('ViewAny:SalaryStructure', 'web'),
    );
    $accountant = User::factory()->create()->assignRole('payroll_accountant');
    $this->actingAs($accountant);

    $employee = Employee::factory()->create(['first_name' => 'Anita', 'last_name' => 'Shrestha']);
    SalaryStructure::create(['employee_id' => $employee->id, 'basic_salary' => 45000, 'effective_from' => '2020-01-01']);

    Livewire::test(ListEmployees::class)->assertSee('45,000');
});

test('a user without salary-structure access never sees salary figures in the employee list', function () {
    // hr_admin can fully manage employees but salary is payroll's domain
    // throughout this app (RolePermissionSeeder never grants hr_admin
    // SalaryStructure) — the column must be hidden entirely, same
    // reasoning as the ->assertDontSee($user->password) case in
    // UserResourceTest.
    Role::findOrCreate('hr_admin', 'web')->givePermissionTo(
        Permission::findOrCreate('ViewAny:Employee', 'web'),
    );
    $hr = User::factory()->create()->assignRole('hr_admin');
    $this->actingAs($hr);

    $employee = Employee::factory()->create(['first_name' => 'Anita', 'last_name' => 'Shrestha']);
    SalaryStructure::create(['employee_id' => $employee->id, 'basic_salary' => 45000, 'effective_from' => '2020-01-01']);

    Livewire::test(ListEmployees::class)->assertDontSee('45,000');
});

test('an employee with no salary structure shows a placeholder, not an error', function () {
    Role::findOrCreate('payroll_accountant', 'web')->givePermissionTo(
        Permission::findOrCreate('ViewAny:Employee', 'web'),
        Permission::findOrCreate('ViewAny:SalaryStructure', 'web'),
    );
    $accountant = User::factory()->create()->assignRole('payroll_accountant');
    $this->actingAs($accountant);

    Employee::factory()->create(); // no SalaryStructure at all

    Livewire::test(ListEmployees::class)->assertSee('Not set');
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
