<?php

namespace App\Models;

use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A single node in the chart of accounts (double-entry bookkeeping — Phase
 * 4a). `account_type` is one of five fixed, structural categories; see the
 * migration for why that's a raw enum column rather than a lookup table.
 */
#[Fillable(['code', 'name', 'account_type', 'parent_account_id', 'is_active'])]
class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use HasFactory;

    public const TYPE_ASSET = 'asset';

    public const TYPE_LIABILITY = 'liability';

    public const TYPE_EQUITY = 'equity';

    public const TYPE_REVENUE = 'revenue';

    public const TYPE_EXPENSE = 'expense';

    /** @return array<string, string> */
    public static function accountTypeOptions(): array
    {
        return [
            self::TYPE_ASSET => 'Asset',
            self::TYPE_LIABILITY => 'Liability',
            self::TYPE_EQUITY => 'Equity',
            self::TYPE_REVENUE => 'Revenue',
            self::TYPE_EXPENSE => 'Expense',
        ];
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Account, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_account_id');
    }

    /** @return HasMany<Account, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_account_id');
    }

    /** @return HasMany<JournalLine, $this> */
    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }
}
