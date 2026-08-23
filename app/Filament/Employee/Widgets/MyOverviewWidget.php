<?php

namespace App\Filament\Employee\Widgets;

use App\Filament\Employee\Resources\LeaveRequests\LeaveRequestResource;
use App\Filament\Employee\Resources\Payslips\PayslipResource;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\PayrollRun;
use App\Models\Payslip;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

/**
 * The self-service half of DESIGN.md D2 — "my pending requests" and "my
 * latest payslip" — shown above the clock in/out content on the Attendance
 * landing page (no separate Dashboard page in this panel, DESIGN.md E1; see
 * Attendance::getHeaderWidgets()). "Clock in/out state" is already the
 * Attendance page's own primary content, not duplicated here.
 *
 * No canView() permission gate: unlike the admin panel's OperationalOverview,
 * this data is inherently self-scoped (an employee's own requests/payslip),
 * not a cross-employee query needing a ViewAny permission check.
 */
class MyOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = -10;

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $employee = auth()->user()?->employee;

        if (! $employee instanceof Employee) {
            return [];
        }

        return [
            $this->pendingRequests($employee),
            $this->latestPayslip($employee),
        ];
    }

    protected function pendingRequests(Employee $employee): Stat
    {
        $count = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('status', LeaveRequest::STATUS_PENDING)
            ->count();

        return Stat::make('My pending requests', (string) $count)
            ->description($count > 0 ? 'Awaiting a decision' : 'Nothing waiting')
            ->descriptionIcon($count > 0 ? Heroicon::OutlinedClock : Heroicon::OutlinedCheckCircle)
            ->color($count > 0 ? 'warning' : 'success')
            ->url(LeaveRequestResource::getUrl());
    }

    protected function latestPayslip(Employee $employee): Stat
    {
        $payslip = Payslip::query()
            ->where('employee_id', $employee->id)
            ->whereHas('payrollRun', fn (Builder $query) => $query->where('status', PayrollRun::STATUS_FINALIZED))
            ->latest('created_at')
            ->first();

        if ($payslip === null) {
            return Stat::make('My latest payslip', 'None yet')
                ->description('Appears once a run including you is finalized')
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color('gray')
                ->url(PayslipResource::getUrl());
        }

        $period = $payslip->payrollRun->period_start->format('M Y');

        return Stat::make('My latest payslip', 'NPR '.number_format((float) $payslip->adjusted_net_pay, 2))
            ->description("Net pay for {$period}")
            ->descriptionIcon(Heroicon::OutlinedBanknotes)
            ->color('gray')
            ->url(PayslipResource::getUrl());
    }
}
