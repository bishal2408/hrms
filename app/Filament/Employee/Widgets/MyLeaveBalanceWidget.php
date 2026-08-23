<?php

namespace App\Filament\Employee\Widgets;

use App\Filament\Employee\Resources\LeaveRequests\LeaveRequestResource;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Services\LeaveBalanceService;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * "My leave balance by type" (DESIGN.md D2) — one stat per balance-tracked
 * leave type, reusing LeaveBalanceService rather than recomputing the
 * entitlement-minus-approved-days formula that already lives there (see its
 * own docblock for why balances are computed live, not stored).
 *
 * Only leave types with a fixed `default_entitlement_days` are shown — a
 * type with none (unpaid leave, public holidays) isn't balance-tracked at
 * all per LeaveBalanceService::remainingDays(), so a stat for it would
 * always read "0 of —" and add noise rather than information.
 *
 * A separate widget from MyOverviewWidget, not folded into the same stats
 * row: leave balance is inherently multi-valued (one figure per type)
 * rather than the single unrelated figures MyOverviewWidget shows, so it
 * reads better as its own grouped section (ui-ux-pro-max: group related
 * items, don't cram unrelated ones into one row).
 */
class MyLeaveBalanceWidget extends StatsOverviewWidget
{
    protected static ?int $sort = -5;

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $employee = auth()->user()?->employee;

        if (! $employee instanceof Employee) {
            return [];
        }

        $balanceService = app(LeaveBalanceService::class);

        return LeaveType::query()
            ->whereNotNull('default_entitlement_days')
            ->get()
            ->map(fn (LeaveType $leaveType): Stat => $this->balanceStat($employee, $leaveType, $balanceService))
            ->all();
    }

    protected function balanceStat(Employee $employee, LeaveType $leaveType, LeaveBalanceService $balanceService): Stat
    {
        $remaining = $balanceService->remainingDays($employee, $leaveType);
        $entitlement = $leaveType->default_entitlement_days;

        return Stat::make($leaveType->name, "{$remaining} of {$entitlement} days")
            ->description($remaining > 0 ? 'Remaining this fiscal year' : 'Fully used this fiscal year')
            ->descriptionIcon($remaining > 0 ? Heroicon::OutlinedCalendarDays : Heroicon::OutlinedExclamationTriangle)
            ->color($remaining > 0 ? 'gray' : 'warning')
            ->url(LeaveRequestResource::getUrl());
    }
}
