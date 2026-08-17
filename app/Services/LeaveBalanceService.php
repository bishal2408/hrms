<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;

/**
 * Leave balances, computed live rather than stored (decision: 2026-08-17).
 *
 * Balance = the leave type's entitlement minus approved days whose request
 * *started* within the current Nepali fiscal year. Nothing here persists a
 * balance row, so there is no year-roll job and nothing to drift or
 * reconcile — the tradeoff is no carry-forward and no manual adjustments,
 * which is an explicit, deliberate scope cut for this slice (see
 * docs/ROADMAP.md 2b). A leave type with no `default_entitlement_days` (e.g.
 * public holidays, unpaid leave) is not balance-tracked at all: entitlement
 * and remaining are both null, and nothing blocks requesting it.
 */
class LeaveBalanceService
{
    /**
     * Approved days taken against a leave type in the fiscal year containing
     * `$asOf` (defaults to now).
     */
    public function usedDays(Employee $employee, LeaveType $leaveType, ?\DateTimeInterface $asOf = null): int
    {
        $fiscalYear = NepaliCalendar::fiscalYearFor($asOf ?? now());

        return (int) LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $leaveType->id)
            ->status(LeaveRequest::STATUS_APPROVED)
            // Not whereBetween(): SQLite stores a `date`-cast column as
            // '2026-07-30 00:00:00', which string-compares as *greater than*
            // a plain '2026-07-30' upper bound and silently excludes that
            // boundary day. whereDate() is the portable comparison —
            // MySQL's native DATE column never had this problem, which is
            // exactly why it went unnoticed until a SQLite-run test hit it.
            ->whereDate('start_date', '>=', $fiscalYear['start_date'])
            ->whereDate('start_date', '<=', $fiscalYear['end_date'])
            ->sum('days');
    }

    /**
     * Days still available, or null when the type has no fixed entitlement
     * (not balance-tracked).
     */
    public function remainingDays(Employee $employee, LeaveType $leaveType, ?\DateTimeInterface $asOf = null): ?int
    {
        if ($leaveType->default_entitlement_days === null) {
            return null;
        }

        return max(0, $leaveType->default_entitlement_days - $this->usedDays($employee, $leaveType, $asOf));
    }
}
