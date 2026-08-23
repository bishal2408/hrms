<?php

namespace App\Models;

use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A sales invoice. `InvoiceService` is the only place a row is written —
 * see it for why an issued invoice is immutable (cancel-don't-delete,
 * CLAUDE.md) and how its GL posting is found (journal_entries.reference_type
 * = 'invoice', reference_id = this row's id — not a stored FK, using the
 * polymorphic-tag columns Phase 4a's schema already carries for exactly
 * this).
 */
#[Fillable(['customer_id', 'fiscal_year', 'sequence', 'invoice_number', 'issue_date', 'subtotal', 'vat_amount', 'total', 'notes'])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    public const STATUS_ISSUED = 'issued';

    public const STATUS_CANCELLED = 'cancelled';

    public const REFERENCE_TYPE = 'invoice';

    /** @return array<string, string> */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_ISSUED => 'Issued',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    /** The one place status colour is decided (DESIGN.md T8). */
    public static function statusColor(string $status): string
    {
        return match ($status) {
            self::STATUS_ISSUED => 'success',
            self::STATUS_CANCELLED => 'gray',
            default => 'gray',
        };
    }

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'cancelled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return HasMany<InvoiceLineItem, $this> */
    public function lineItems(): HasMany
    {
        return $this->hasMany(InvoiceLineItem::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /** The ledger posting for this invoice, via the polymorphic reference — not a stored FK. */
    public function journalEntry(): ?JournalEntry
    {
        return JournalEntry::query()
            ->where('reference_type', self::REFERENCE_TYPE)
            ->where('reference_id', $this->id)
            ->first();
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }
}
