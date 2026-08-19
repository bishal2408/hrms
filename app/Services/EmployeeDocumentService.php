<?php

namespace App\Services;

use App\Models\DocumentType;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\User;

/**
 * The only place an EmployeeDocument row is written for an upload. Its
 * single job is stamping `uploaded_by` from the authenticated user rather
 * than trusting it from form input — that field is deliberately outside
 * EmployeeDocument's `$fillable`, so mass assignment silently drops it
 * (CLAUDE.md's Coding conventions) if this isn't done explicitly. Deleting a
 * document needs no service method: it's a plain `$document->delete()`
 * behind the model's own file-cleanup event and the resource's policy
 * check — nothing left to centralize.
 */
class EmployeeDocumentService
{
    /**
     * @param  string  $path  Already-stored path on the private 'local' disk
     *                        — Filament's FileUpload writes the file before
     *                        this is called; this method only persists the
     *                        metadata row.
     */
    public function upload(
        Employee $employee,
        DocumentType $documentType,
        string $path,
        string $originalFilename,
        ?string $notes,
        User $uploadedBy,
    ): EmployeeDocument {
        $document = new EmployeeDocument([
            'employee_id' => $employee->id,
            'document_type_id' => $documentType->id,
            'path' => $path,
            'original_filename' => $originalFilename,
            'notes' => $notes,
        ]);
        $document->uploaded_by = $uploadedBy->id;
        $document->save();

        return $document;
    }
}
