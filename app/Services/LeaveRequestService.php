<?php

namespace App\Services;

use App\Exceptions\LeaveBalanceExceededException;
use App\Exceptions\LeaveRequestForbiddenException;
use App\Exceptions\LeaveRequestNotPendingException;
use App\Exceptions\OverlappingLeaveRequestException;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * The leave request lifecycle: submit → approve/reject, or submit → cancel.
 *
 * Both Filament resources (admin approval, employee self-service) call this
 * rather than writing to LeaveRequest directly, so the balance check,
 * overlap check and who-may-decide rule exist in exactly one place — the
 * same reasoning as AttendanceService for attendance.
 */
class LeaveRequestService
{
    public function __construct(
        private readonly LeaveBalanceService $balances,
    ) {}

    /**
     * @throws OverlappingLeaveRequestException When the range overlaps an existing pending/approved request.
     * @throws LeaveBalanceExceededException When the leave type is balance-tracked and not enough remains.
     */
    public function submit(
        Employee $employee,
        LeaveType $leaveType,
        string $startDate,
        string $endDate,
        ?string $reason = null,
    ): LeaveRequest {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        if ($this->hasOverlap($employee, $start, $end)) {
            throw new OverlappingLeaveRequestException;
        }

        $days = (int) $start->diffInDays($end) + 1;
        $remaining = $this->balances->remainingDays($employee, $leaveType, $start);

        if ($remaining !== null && $days > $remaining) {
            throw new LeaveBalanceExceededException($days, $remaining);
        }

        $request = new LeaveRequest([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'reason' => $reason,
        ]);
        // Direct property assignment, not mass assignment: 'status' is
        // deliberately outside $fillable. The migration also defaults it to
        // 'pending', but that DB default never makes it back into this
        // in-memory instance without a refresh — set it explicitly so the
        // object returned to the caller is correct immediately.
        $request->status = LeaveRequest::STATUS_PENDING;
        $request->save();

        return $request;
    }

    /**
     * @throws LeaveRequestNotPendingException
     * @throws LeaveRequestForbiddenException
     */
    public function approve(LeaveRequest $request, User $approver): LeaveRequest
    {
        $this->guardPendingAndStanding($request, $approver, 'approve');

        // forceFill, not update(): status/decided_by/decided_at are
        // deliberately excluded from $fillable so no form can mass-assign
        // them — this method is the only place they may be set.
        $request->forceFill([
            'status' => LeaveRequest::STATUS_APPROVED,
            'decided_by' => $approver->id,
            'decided_at' => Carbon::now(),
        ])->save();

        return $request->refresh();
    }

    /**
     * @throws LeaveRequestNotPendingException
     * @throws LeaveRequestForbiddenException
     */
    public function reject(LeaveRequest $request, User $approver, string $reason): LeaveRequest
    {
        if (blank($reason)) {
            throw new InvalidArgumentException('A reason is required to reject a leave request.');
        }

        $this->guardPendingAndStanding($request, $approver, 'reject');

        $request->forceFill([
            'status' => LeaveRequest::STATUS_REJECTED,
            'decided_by' => $approver->id,
            'decided_at' => Carbon::now(),
            'decision_note' => $reason,
        ])->save();

        return $request->refresh();
    }

    /**
     * @throws LeaveRequestNotPendingException
     * @throws LeaveRequestForbiddenException
     */
    public function cancel(LeaveRequest $request, User $user): LeaveRequest
    {
        if (! $request->isPending()) {
            throw new LeaveRequestNotPendingException;
        }

        if ($request->employee->user_id !== $user->id) {
            throw new LeaveRequestForbiddenException('cancel');
        }

        $request->forceFill(['status' => LeaveRequest::STATUS_CANCELLED])->save();

        return $request->refresh();
    }

    /**
     * Whether the given user has standing to approve or reject this request:
     * HR/super_admin may decide anyone's, a manager may decide their direct
     * reports', and nobody — including HR — may decide their own.
     */
    public function canDecide(LeaveRequest $request, User $user): bool
    {
        if ($request->employee->user_id === $user->id) {
            return false;
        }

        if ($user->hasAnyRole(['super_admin', 'hr_admin'])) {
            return true;
        }

        return $request->employee->manager?->user_id === $user->id;
    }

    private function guardPendingAndStanding(LeaveRequest $request, User $user, string $action): void
    {
        if (! $request->isPending()) {
            throw new LeaveRequestNotPendingException;
        }

        if (! $this->canDecide($request, $user)) {
            throw new LeaveRequestForbiddenException($action);
        }
    }

    private function hasOverlap(Employee $employee, Carbon $start, Carbon $end): bool
    {
        return LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->whereIn('status', [LeaveRequest::STATUS_PENDING, LeaveRequest::STATUS_APPROVED])
            // whereDate(), not where('col', '<=', ...): SQLite stores a
            // `date`-cast column with a '00:00:00' suffix, which breaks plain
            // string comparison exactly on the boundary date (a request
            // starting/ending on the same day as the range being checked).
            // MySQL's native DATE column masked this. whereDate() compares
            // correctly on both.
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->exists();
    }
}
