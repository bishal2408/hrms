<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

#[Fillable(['marital_status', 'lower_bound', 'upper_bound', 'rate_percent', 'effective_from', 'notes'])]
class TaxSlab extends Model
{
    public const MARITAL_SINGLE = 'single';

    public const MARITAL_MARRIED = 'married';

    /**
     * Value => label for every marital status a slab table can apply to. The
     * single source of these labels: forms and tables read from here rather
     * than re-typing them.
     *
     * @return array<string, string>
     */
    public static function maritalStatusOptions(): array
    {
        return [
            self::MARITAL_SINGLE => 'Single',
            self::MARITAL_MARRIED => 'Married',
        ];
    }

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'lower_bound' => 'decimal:2',
            'upper_bound' => 'decimal:2',
            'rate_percent' => 'decimal:2',
        ];
    }

    /**
     * The full slab table (all brackets) effective as of a given date for a
     * marital status, ordered low to high. A slab table is replaced as a
     * whole when rates change, so all brackets of one version share the
     * same effective_from date.
     *
     * @return Collection<int, self>
     */
    public static function slabsFor(string $maritalStatus, DateTimeInterface|string|null $date = null): Collection
    {
        $date = $date ? Carbon::parse($date) : Carbon::now();

        $latestEffectiveFrom = static::query()
            ->where('marital_status', $maritalStatus)
            ->where('effective_from', '<=', $date)
            ->max('effective_from');

        if ($latestEffectiveFrom === null) {
            return new Collection;
        }

        return static::query()
            ->where('marital_status', $maritalStatus)
            ->where('effective_from', $latestEffectiveFrom)
            ->orderBy('lower_bound')
            ->get();
    }
}
