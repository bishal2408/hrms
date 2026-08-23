<?php

namespace App\Models;

use Database\Factories\VatRateFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Effective-dated, like PayrollRate/TaxSlab — see the migration for why.
 * `currentFor()` mirrors PayrollRate's own lookup shape exactly.
 */
#[Fillable(['rate_percent', 'effective_from', 'notes'])]
class VatRate extends Model
{
    /** @use HasFactory<VatRateFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'rate_percent' => 'decimal:2',
        ];
    }

    public function scopeAsOf(Builder $query, DateTimeInterface|string|null $date = null): Builder
    {
        return $query
            ->where('effective_from', '<=', $date ? Carbon::parse($date) : Carbon::now())
            ->orderByDesc('effective_from');
    }

    /** The VAT rate in effect on a given date (defaults to today). */
    public static function currentFor(DateTimeInterface|string|null $date = null): ?self
    {
        return static::query()->asOf($date)->first();
    }
}
