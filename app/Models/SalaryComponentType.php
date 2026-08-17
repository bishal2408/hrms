<?php

namespace App\Models;

use Database\Factories\SalaryComponentTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'code', 'component_type', 'is_active'])]
class SalaryComponentType extends Model
{
    /** @use HasFactory<SalaryComponentTypeFactory> */
    use HasFactory;

    public const TYPE_ALLOWANCE = 'allowance';

    public const TYPE_DEDUCTION = 'deduction';

    /** @return array<string, string> */
    public static function componentTypeOptions(): array
    {
        return [
            self::TYPE_ALLOWANCE => 'Allowance',
            self::TYPE_DEDUCTION => 'Deduction',
        ];
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<SalaryStructureItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(SalaryStructureItem::class);
    }

    /** @param  Builder<self>  $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** @param  Builder<self>  $query */
    public function scopeAllowances(Builder $query): Builder
    {
        return $query->where('component_type', self::TYPE_ALLOWANCE);
    }

    /** @param  Builder<self>  $query */
    public function scopeDeductions(Builder $query): Builder
    {
        return $query->where('component_type', self::TYPE_DEDUCTION);
    }
}
