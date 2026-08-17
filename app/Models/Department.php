<?php

namespace App\Models;

use Database\Factories\DepartmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'code', 'is_active'])]
class Department extends Model
{
    /** @use HasFactory<DepartmentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<Employee, $this> */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /** @param  Builder<self>  $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
