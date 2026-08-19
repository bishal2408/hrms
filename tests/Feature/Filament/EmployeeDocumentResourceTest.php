<?php

use App\Filament\Employee\Resources\EmployeeDocuments\Pages\ManageEmployeeDocuments;
use App\Models\DocumentType;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('employee', 'web')->givePermissionTo(
        Permission::findOrCreate('ViewAny:EmployeeDocument', 'web'),
        Permission::findOrCreate('View:EmployeeDocument', 'web'),
    );

    Filament::setCurrentPanel(Filament::getPanel('employee'));
});

test('an employee sees their own documents', function () {
    $user = User::factory()->create()->assignRole('employee');
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $document = EmployeeDocument::factory()->create([
        'employee_id' => $employee->id,
        'document_type_id' => DocumentType::factory()->create(),
    ]);

    $this->actingAs($user);

    Livewire::test(ManageEmployeeDocuments::class)
        ->assertCanSeeTableRecords([$document]);
});

test('an employee never sees another employee\'s documents', function () {
    $user = User::factory()->create()->assignRole('employee');
    Employee::factory()->create(['user_id' => $user->id]);

    $other = Employee::factory()->create();
    $document = EmployeeDocument::factory()->create([
        'employee_id' => $other->id,
        'document_type_id' => DocumentType::factory()->create(),
    ]);

    $this->actingAs($user);

    Livewire::test(ManageEmployeeDocuments::class)
        ->assertCanNotSeeTableRecords([$document]);
});

test('no create action exists — documents are never uploaded here', function () {
    $user = User::factory()->create()->assignRole('employee');
    Employee::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test(ManageEmployeeDocuments::class)
        ->assertActionDoesNotExist('create');
});
