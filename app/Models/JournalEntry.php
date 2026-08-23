<?php

namespace App\Models;

use Database\Factories\JournalEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A posted, balanced (debits == credits) double-entry transaction.
 * `JournalEntryService` is the only place a row is written — see it for why
 * an entry is immutable once posted and how a mistake gets corrected
 * (`reverse()`, never edit/delete).
 */
#[Fillable(['entry_date', 'description', 'reference_type', 'reference_id', 'reverses_journal_entry_id'])]
class JournalEntry extends Model
{
    /** @use HasFactory<JournalEntryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
        ];
    }

    /** @return HasMany<JournalLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    /** @return BelongsTo<User, $this> */
    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    /** @return BelongsTo<self, $this> The original entry this one reverses, if it is a reversal. */
    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_journal_entry_id');
    }

    /** @return HasMany<self, $this> The reversal posted against this entry, if any (at most one). */
    public function reversals(): HasMany
    {
        return $this->hasMany(self::class, 'reverses_journal_entry_id');
    }

    public function isReversed(): bool
    {
        return $this->reversals()->exists();
    }
}
