<?php

namespace App\Models;

use Database\Factories\EmployeeDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A single uploaded file attached to an employee (contract, citizenship
 * copy, certificate, etc.) — sensitive PII, stored on the private 'local'
 * disk and only ever reachable through an authorized download route, never
 * a public URL. `uploaded_by` is intentionally outside `$fillable`: it is
 * the acting user, set directly by EmployeeDocumentService rather than
 * trusted from form input (CLAUDE.md's mass-assignment lesson).
 */
#[Fillable(['employee_id', 'document_type_id', 'path', 'original_filename', 'notes'])]
class EmployeeDocument extends Model
{
    /** @use HasFactory<EmployeeDocumentFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        // Deleting the row and orphaning the file on disk would be worse
        // than deleting both — there is no soft-delete/restore UI for these,
        // so a delete here is meant to be final either way.
        static::deleting(function (EmployeeDocument $document): void {
            Storage::disk('local')->delete($document->path);
        });
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<DocumentType, $this> */
    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
