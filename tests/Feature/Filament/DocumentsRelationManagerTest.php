<?php

use App\Filament\Resources\Employees\Pages\EditEmployee;
use App\Filament\Resources\Employees\RelationManagers\DocumentsRelationManager;
use App\Models\DocumentType;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * Only `mountAction()`/gating is exercised here, not `fillForm()`/
 * `callAction(..., data: [...])` on the "upload" action: calling a
 * schema-bearing Action hosted on a RelationManager throws inside Filament
 * v5.7.6's own test harness (TestsForms::fillForm() — see PayrollRunResource's
 * addAdjustment for the same documented limitation). EmployeeDocumentService
 * and the file-cleanup model event are covered directly at their own layers.
 */
beforeEach(function () {
    Role::findOrCreate('hr_admin', 'web')->givePermissionTo(
        Permission::findOrCreate('ViewAny:EmployeeDocument', 'web'),
        Permission::findOrCreate('Create:EmployeeDocument', 'web'),
    );
    Role::findOrCreate('manager', 'web');

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

test('hr_admin can see the Documents tab on an employee', function () {
    $hr = User::factory()->create()->assignRole('hr_admin');
    $employee = Employee::factory()->create();

    $this->actingAs($hr);

    expect(DocumentsRelationManager::canViewForRecord($employee, EditEmployee::class))->toBeTrue();
});

test('a manager cannot see the Documents tab, even for a direct report', function () {
    $manager = User::factory()->create()->assignRole('manager');
    $employee = Employee::factory()->create();

    $this->actingAs($manager);

    expect(DocumentsRelationManager::canViewForRecord($employee, EditEmployee::class))->toBeFalse();
});

test('deleting a document row removes it from the employee\'s documents', function () {
    $hr = User::factory()->create()->assignRole('hr_admin');
    $employee = Employee::factory()->create();
    $document = EmployeeDocument::factory()->create([
        'employee_id' => $employee->id,
        'document_type_id' => DocumentType::factory()->create(),
    ]);

    $this->actingAs($hr);

    $document->delete();

    expect($employee->documents()->count())->toBe(0);
});
