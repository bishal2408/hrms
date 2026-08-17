<?php

namespace App\Models;

use Database\Factories\LeaveRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One employee's request for time off against a leave type, carrying its own
 * approve/reject/cancel lifecycle. Balances are never stored against this —
 * see `LeaveBalanceService`, which sums approved requests live.
 */
#[Fillable(['employee_id', 'leave_type_id', 'start_date', 'end_date', 'reason'])]
class LeaveRequest extends Model
{
    /** @use HasFactory<LeaveRequestFactory> */
    use HasFactory;

    use SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    /** @return array<string, string> */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    /** The one place status colour is decided (DESIGN.md T8). */
    public static function statusColor(string $status): string
    {
        return match ($status) {
            self::STATUS_APPROVED => 'success',
            self::STATUS_PENDING => 'warning',
            self::STATUS_REJECTED => 'danger',
            self::STATUS_CANCELLED => 'gray',
            default => 'gray',
        };
    }

    protected static function booted(): void
    {
        // The one place `days` is derived, so create and edit can never
        // disagree with each other about how a date range is counted.
        static::saving(function (self $request): void {
            if ($request->start_date && $request->end_date) {
                $request->days = (int) $request->start_date->diffInDays($request->end_date) + 1;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'decided_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<LeaveType, $this> */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    /** @return BelongsTo<User, $this> */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Limit the query to requests the given user is allowed to see — the same
     * rule as `Employee::scopeVisibleTo()`, applied through the requester
     * rather than duplicated: HR/payroll/super_admin see everyone's, everyone
     * else sees their own plus their direct reports'.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereIn('employee_id', Employee::query()->visibleTo($user)->select('id'));
    }

    /** @param  Builder<self>  $query */
    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }
}
