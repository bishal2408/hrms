<?php

namespace App\Models;

use Database\Factories\PayrollRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One payroll calculation across every eligible employee for a period.
 * `PayrollRunService` is the only place a run's status may change — see it
 * for the draft → finalized lifecycle and why a finalized run's payslips are
 * never edited or deleted, only corrected via PayslipAdjustment.
 *
 * Deliberately not soft-deleting: the only delete path is
 * PayrollRunService::discard(), which only ever runs on a draft that "never
 * paid anyone, so there is nothing to preserve." Soft-deleting bought nothing
 * there and actively broke re-running payroll for the same period — the
 * unique index on (period_start, period_end) has no concept of `deleted_at`,
 * so a soft-deleted row kept blocking its period forever (fixed in the
 * remove_soft_deletes_from_payroll_runs_table migration). A finalized run is
 * never deleted by anything, so this trait had no real use case to begin
 * with — don't re-add it without solving the unique-index mismatch first.
 */
#[Fillable(['period_start', 'period_end', 'notes'])]
class PayrollRun extends Model
{
    /** @use HasFactory<PayrollRunFactory> */
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_FINALIZED = 'finalized';

    public const STATUS_CANCELLED = 'cancelled';

    /** @return array<string, string> */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_FINALIZED => 'Finalized',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    /** The one place status colour is decided (DESIGN.md T8). */
    public static function statusColor(string $status): string
    {
        return match ($status) {
            self::STATUS_FINALIZED => 'success',
            self::STATUS_DRAFT => 'warning',
            self::STATUS_CANCELLED => 'gray',
            default => 'gray',
        };
    }

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'calculated_at' => 'datetime',
            'finalized_at' => 'datetime',
            'skipped_employees' => 'array',
        ];
    }

    /** @return HasMany<Payslip, $this> */
    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isFinalized(): bool
    {
        return $this->status === self::STATUS_FINALIZED;
    }
}
