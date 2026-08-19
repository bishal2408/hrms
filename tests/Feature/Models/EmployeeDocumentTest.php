<?php

use App\Models\EmployeeDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('deleting a document also deletes its file from disk', function () {
    Storage::fake('local');

    $path = UploadedFile::fake()->create('contract.pdf', 10)->store('employee-documents/1', 'local');
    $document = EmployeeDocument::factory()->create(['path' => $path]);

    Storage::disk('local')->assertExists($path);

    $document->delete();

    Storage::disk('local')->assertMissing($path);
});
