<?php

namespace App\Models;

use Database\Factories\InvoiceLineItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One frozen line of an Invoice — see Invoice's docblock for why it's never edited after issue. */
#[Fillable(['invoice_id', 'description', 'quantity', 'unit_price', 'is_vatable', 'amount'])]
class InvoiceLineItem extends Model
{
    /** @use HasFactory<InvoiceLineItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_vatable' => 'boolean',
        ];
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
