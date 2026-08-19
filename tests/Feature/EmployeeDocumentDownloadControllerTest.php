<?php

use App\Models\DocumentType;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');

    Role::findOrCreate('hr_admin', 'web');
    Role::findOrCreate('payroll_accountant', 'web');
    Role::findOrCreate('manager', 'web');
    Role::findOrCreate('employee', 'web');
});

function makeEmployeeDocument(): EmployeeDocument
{
    $path = UploadedFile::fake()->create('contract.pdf', 10)->store('employee-documents/1', 'local');

    return EmployeeDocument::factory()->create([
        'employee_id' => Employee::factory()->create(),
        'document_type_id' => DocumentType::factory()->create(),
        'path' => $path,
        'original_filename' => 'contract.pdf',
    ]);
}

test('hr_admin can download any employee\'s document', function () {
    $hr = User::factory()->create()->assignRole('hr_admin');
    $document = makeEmployeeDocument();

    $this->actingAs($hr)
        ->get(route('employee-documents.download', $document))
        ->assertOk();
});

test('payroll_accountant can download any employee\'s document', function () {
    $payroll = User::factory()->create()->assignRole('payroll_accountant');
    $document = makeEmployeeDocument();

    $this->actingAs($payroll)
        ->get(route('employee-documents.download', $document))
        ->assertOk();
});

test('an employee can download their own document', function () {
    $user = User::factory()->create()->assignRole('employee');
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $path = UploadedFile::fake()->create('contract.pdf', 10)->store('employee-documents/1', 'local');
    $document = EmployeeDocument::factory()->create([
        'employee_id' => $employee->id,
        'document_type_id' => DocumentType::factory()->create(),
        'path' => $path,
    ]);

    $this->actingAs($user)
        ->get(route('employee-documents.download', $document))
        ->assertOk();
});

test('an employee cannot download someone else\'s document', function () {
    $user = User::factory()->create()->assignRole('employee');
    Employee::factory()->create(['user_id' => $user->id]);
    $document = makeEmployeeDocument();

    $this->actingAs($user)
        ->get(route('employee-documents.download', $document))
        ->assertForbidden();
});

test('a manager cannot download a direct report\'s document, unlike the employee record itself', function () {
    $manager = User::factory()->create()->assignRole('manager');
    $managerEmployee = Employee::factory()->create(['user_id' => $manager->id]);

    $path = UploadedFile::fake()->create('contract.pdf', 10)->store('employee-documents/1', 'local');
    $document = EmployeeDocument::factory()->create([
        'employee_id' => Employee::factory()->create(['manager_id' => $managerEmployee->id]),
        'document_type_id' => DocumentType::factory()->create(),
        'path' => $path,
    ]);

    $this->actingAs($manager)
        ->get(route('employee-documents.download', $document))
        ->assertForbidden();
});

test('an unauthenticated request is forbidden, not shown the file', function () {
    $document = makeEmployeeDocument();

    $this->get(route('employee-documents.download', $document))->assertForbidden();
});
