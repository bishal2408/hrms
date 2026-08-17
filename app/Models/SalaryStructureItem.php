<?php

namespace App\Models;

use Database\Factories\SalaryStructureItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['salary_structure_id', 'salary_component_type_id', 'amount'])]
class SalaryStructureItem extends Model
{
    /** @use HasFactory<SalaryStructureItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<SalaryStructure, $this> */
    public function salaryStructure(): BelongsTo
    {
        return $this->belongsTo(SalaryStructure::class);
    }

    /** @return BelongsTo<SalaryComponentType, $this> */
    public function componentType(): BelongsTo
    {
        return $this->belongsTo(SalaryComponentType::class, 'salary_component_type_id');
    }
}
