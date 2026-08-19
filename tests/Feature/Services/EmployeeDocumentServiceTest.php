<?php

use App\Models\DocumentType;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\User;
use App\Services\EmployeeDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new EmployeeDocumentService;
});

test('uploading stamps the acting user as uploaded_by, not from form input', function () {
    $employee = Employee::factory()->create();
    $type = DocumentType::factory()->create();
    $uploader = User::factory()->create();

    $document = $this->service->upload(
        $employee,
        $type,
        'employee-documents/1/abc123.pdf',
        'contract.pdf',
        'Signed copy',
        $uploader,
    );

    expect($document->employee_id)->toBe($employee->id)
        ->and($document->document_type_id)->toBe($type->id)
        ->and($document->path)->toBe('employee-documents/1/abc123.pdf')
        ->and($document->original_filename)->toBe('contract.pdf')
        ->and($document->notes)->toBe('Signed copy')
        ->and($document->uploaded_by)->toBe($uploader->id);
});

test('notes are optional', function () {
    $document = $this->service->upload(
        Employee::factory()->create(),
        DocumentType::factory()->create(),
        'employee-documents/1/abc123.pdf',
        'contract.pdf',
        null,
        User::factory()->create(),
    );

    expect($document->notes)->toBeNull()
        ->and(EmployeeDocument::count())->toBe(1);
});
