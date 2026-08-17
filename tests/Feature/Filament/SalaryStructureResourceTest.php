<?php

use App\Filament\Resources\SalaryStructures\Pages\CreateSalaryStructure;
use App\Filament\Resources\SalaryStructures\Pages\EditSalaryStructure;
use App\Models\Employee;
use App\Models\SalaryComponentType;
use App\Models\SalaryStructure;
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

test('an admin can create a salary structure with itemized allowances, BS date stored as AD', function () {
    $employee = Employee::factory()->create();
    $transport = SalaryComponentType::factory()->create();

    Livewire::test(CreateSalaryStructure::class)
        ->fillForm([
            'employee_id' => $employee->id,
            'basic_salary' => 50000,
            'effective_from' => '2083-04-01',
            'items' => [
                ['salary_component_type_id' => $transport->id, 'amount' => 3000],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $structure = SalaryStructure::sole();

    expect($structure->basic_salary)->toBe('50000.00')
        ->and($structure->effective_from->toDateString())->toBe(NepaliCalendar::bsToAd('2083-04-01')->toDateString())
        ->and($structure->items()->count())->toBe(1)
        ->and($structure->items()->first()->amount)->toBe('3000.00');
});

test('a salary structure with no line items can still be created', function () {
    $employee = Employee::factory()->create();

    Livewire::test(CreateSalaryStructure::class)
        ->fillForm([
            'employee_id' => $employee->id,
            'basic_salary' => 40000,
            'effective_from' => '2083-04-01',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(SalaryStructure::sole()->items()->count())->toBe(0);
});

test('editing a structure loads its stored values and items back into the form', function () {
    $employee = Employee::factory()->create();
    $structure = SalaryStructure::create([
        'employee_id' => $employee->id,
        'basic_salary' => 45000,
        'effective_from' => NepaliCalendar::bsToAd('2083-04-01')->toDateString(),
    ]);
    $transport = SalaryComponentType::factory()->create();
    $structure->items()->create(['salary_component_type_id' => $transport->id, 'amount' => 2500]);

    Livewire::test(EditSalaryStructure::class, ['record' => $structure->getRouteKey()])
        ->assertOk()
        ->assertSchemaStateSet([
            'basic_salary' => '45000.00',
            'effective_from' => '2083-04-01',
        ]);
});

test('the edit page exposes no delete action', function () {
    $structure = SalaryStructure::factory()->create();

    Livewire::test(EditSalaryStructure::class, ['record' => $structure->getRouteKey()])
        ->assertActionDoesNotExist('delete');
});
