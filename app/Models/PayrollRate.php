<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

#[Fillable(['type', 'employee_contribution_percent', 'employer_contribution_percent', 'effective_from', 'notes'])]
class PayrollRate extends Model
{
    public const TYPE_PROVIDENT_FUND = 'provident_fund';

    public const TYPE_SOCIAL_SECURITY_FUND = 'social_security_fund';

    /**
     * Value => label for every contribution type. The single source of these
     * labels: forms and tables read from here rather than re-typing them.
     *
     * @return array<string, string>
     */
    public static function typeOptions(): array
    {
        return [
            self::TYPE_PROVIDENT_FUND => 'Provident Fund',
            self::TYPE_SOCIAL_SECURITY_FUND => 'Social Security Fund',
        ];
    }

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'employee_contribution_percent' => 'decimal:2',
            'employer_contribution_percent' => 'decimal:2',
        ];
    }

    public function scopeAsOf(Builder $query, string $type, DateTimeInterface|string|null $date = null): Builder
    {
        return $query->where('type', $type)
            ->where('effective_from', '<=', $date ? Carbon::parse($date) : Carbon::now())
            ->orderByDesc('effective_from');
    }

    /** The rate in effect for a given type on a given date (defaults to today). */
    public static function currentFor(string $type, DateTimeInterface|string|null $date = null): ?self
    {
        return static::query()->asOf($type, $date)->first();
    }
}
