<?php

namespace App\Models;

use Database\Factories\PayslipAdjustmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A signed correction to an already-finalized Payslip. Never edited or
 * deleted once created — correcting an adjustment means adding another one
 * (CLAUDE.md: reversal/adjustment, never mutate a paid payslip).
 */
#[Fillable(['payslip_id', 'amount', 'reason'])]
class PayslipAdjustment extends Model
{
    /** @use HasFactory<PayslipAdjustmentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Payslip, $this> */
    public function payslip(): BelongsTo
    {
        return $this->belongsTo(Payslip::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
