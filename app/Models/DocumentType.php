<?php

namespace App\Models;

use Database\Factories\DocumentTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A category of employee document (contract, citizenship copy, certificate,
 * etc.) — admin-managed under Setup, mirrors SalaryComponentType/LeaveType.
 */
#[Fillable(['name', 'is_active'])]
class DocumentType extends Model
{
    /** @use HasFactory<DocumentTypeFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<EmployeeDocument, $this> */
    public function employeeDocuments(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }
}
