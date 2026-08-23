<?php

namespace App\Models;

use Database\Factories\JournalLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One debit or credit line of a JournalEntry. Written only by
 * JournalEntryService — see it for the balance validation (exactly one of
 * debit/credit per line, sum(debit) == sum(credit) across the entry).
 */
#[Fillable(['journal_entry_id', 'account_id', 'debit', 'credit', 'description'])]
class JournalLine extends Model
{
    /** @use HasFactory<JournalLineFactory> */
    use HasFactory;

    /** @return BelongsTo<JournalEntry, $this> */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
