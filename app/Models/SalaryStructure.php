<?php

namespace App\Models;

use Database\Factories\SalaryStructureFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * One effective-dated version of an employee's pay: a basic salary plus
 * itemized allowance/deduction lines (`items`). Mirrors PayrollRate/TaxSlab's
 * pattern — a pay change is a new row with a later `effective_from`, never an
 * edit to an old one, so historical payroll stays reproducible.
 */
#[Fillable(['employee_id', 'basic_salary', 'effective_from', 'notes'])]
class SalaryStructure extends Model
{
    /** @use HasFactory<SalaryStructureFactory> */
    use HasFactory;

    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'basic_salary' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return HasMany<SalaryStructureItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(SalaryStructureItem::class);
    }

    /** @param  Builder<self>  $query */
    public function scopeAsOf(Builder $query, int $employeeId, DateTimeInterface|string|null $date = null): Builder
    {
        return $query->where('employee_id', $employeeId)
            ->where('effective_from', '<=', $date ? Carbon::parse($date) : Carbon::now())
            ->orderByDesc('effective_from');
    }

    /** The structure in effect for an employee on a given date (defaults to today). */
    public static function currentFor(int $employeeId, DateTimeInterface|string|null $date = null): ?self
    {
        return static::query()->with('items.componentType')->asOf($employeeId, $date)->first();
    }
}
