<?php

namespace App\Filament\Widgets;

use App\DTOs\VatRegisterReport;
use App\Filament\Pages\PfSsfRemittancePage;
use App\Filament\Pages\TdsReportPage;
use App\Filament\Pages\VatRegisterPage;
use App\Filament\Resources\AttendanceLogs\AttendanceLogResource;
use App\Filament\Resources\Employees\EmployeeResource;
use App\Filament\Resources\LeaveRequests\LeaveRequestResource;
use App\Filament\Resources\PayrollRuns\PayrollRunResource;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\AccountingReportService;
use App\Services\PayrollComplianceReportService;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

/**
 * "What needs my attention today?" (DESIGN.md D2/D3) — the admin panel's
 * primary operational stats, replacing SetupStatusOverview's one-time
 * configuration checklist now that real HR/attendance/leave/payroll data
 * exists to report on. Extended 2026-08-23 (user-requested) with the
 * compliance/accounting figures DESIGN.md D2 originally named for this
 * widget but weren't buildable until Phase 7b's reports existed to read
 * from.
 *
 * D5: every query here is scoped the same way its resource already scopes
 * it (Employee/LeaveRequest via visibleTo($user) — a manager sees their
 * reports, not the whole company; PayrollRun/Invoice are company-wide,
 * matching PayrollRunResource's own lack of per-record scoping), and every
 * stat is omitted entirely — not just hidden — for a user without the
 * matching access, so getStats() never runs a query the viewer isn't
 * authorized for. The four compliance/accounting stats gate on
 * `hasAnyRole(['super_admin', 'payroll_accountant'])`, not a `ViewAny:*`
 * permission — mirrors the destination pages' own canAccess(): a standalone
 * Filament Page (not a Resource) gets no Shield-generated permission (see
 * VatRegisterPage's own docblock), so there is no `ViewAny:VatRegister`
 * permission to check in the first place.
 *
 * D6: every query is bounded (today's date, one period's payslips, or a
 * single latest-record lookup) — nothing scans a full history table. The
 * PF/SSF/TDS pair share one PayrollRun lookup and the sales/VAT pair share
 * one vatRegister() call, rather than each stat re-querying independently.
 */
class OperationalOverview extends StatsOverviewWidget
{
    protected static ?int $sort = -10;

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return [];
        }

        $stats = [];

        if ($user->can('ViewAny:Employee')) {
            $stats[] = $this->headcount($user);
        }

        if ($user->can('ViewAny:AttendanceLog')) {
            $stats[] = $this->attendanceToday($user);
        }

        if ($user->can('ViewAny:LeaveRequest')) {
            $stats[] = $this->pendingLeaveApprovals($user);
            $stats[] = $this->onLeaveToday($user);
        }

        if ($user->can('ViewAny:PayrollRun')) {
            $stats[] = $this->latestPayrollRun();
        }

        if ($user->hasAnyRole(['super_admin', 'payroll_accountant'])) {
            $latestFinalizedRun = PayrollRun::query()
                ->where('status', PayrollRun::STATUS_FINALIZED)
                ->latest('period_start')
                ->first();
            $stats[] = $this->pfSsfDue($latestFinalizedRun);
            $stats[] = $this->tdsDue($latestFinalizedRun);

            $vatReport = app(AccountingReportService::class)->vatRegister(Carbon::now()->startOfMonth(), Carbon::now());
            $stats[] = $this->salesThisMonth($vatReport);
            $stats[] = $this->vatCollectedThisMonth($vatReport);
        }

        return $stats;
    }

    protected function headcount(User $user): Stat
    {
        $count = Employee::query()->visibleTo($user)->active()->count();

        return Stat::make('Headcount', (string) $count)
            ->description('Active employees')
            ->descriptionIcon(Heroicon::OutlinedUsers)
            ->color('gray')
            ->url(EmployeeResource::getUrl());
    }

    protected function attendanceToday(User $user): Stat
    {
        $activeCount = Employee::query()->visibleTo($user)->active()->count();

        $presentCount = AttendanceLog::query()
            ->whereDate('date', Carbon::today()->toDateString())
            ->whereHas('employee', fn ($query) => $query->visibleTo($user)->active())
            ->distinct()
            ->count('employee_id');

        $absentCount = max(0, $activeCount - $presentCount);

        return Stat::make('Present today', "{$presentCount} of {$activeCount}")
            ->description($absentCount > 0 ? "{$absentCount} not yet clocked in" : 'Everyone is in')
            ->descriptionIcon($absentCount > 0 ? Heroicon::OutlinedExclamationTriangle : Heroicon::OutlinedCheckCircle)
            ->color($absentCount > 0 ? 'warning' : 'success')
            ->url(AttendanceLogResource::getUrl());
    }

    protected function pendingLeaveApprovals(User $user): Stat
    {
        $count = LeaveRequest::query()
            ->visibleTo($user)
            ->where('status', LeaveRequest::STATUS_PENDING)
            ->count();

        return Stat::make('Pending leave approvals', (string) $count)
            ->description($count > 0 ? 'Waiting on a decision' : 'Nothing waiting')
            ->descriptionIcon($count > 0 ? Heroicon::OutlinedClock : Heroicon::OutlinedCheckCircle)
            ->color($count > 0 ? 'warning' : 'success')
            ->url(LeaveRequestResource::getUrl());
    }

    protected function onLeaveToday(User $user): Stat
    {
        $today = Carbon::today()->toDateString();

        $count = LeaveRequest::query()
            ->visibleTo($user)
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->count();

        return Stat::make('On leave today', (string) $count)
            ->description($count > 0 ? 'Approved and away today' : 'Everyone is available')
            ->descriptionIcon($count > 0 ? Heroicon::OutlinedUserMinus : Heroicon::OutlinedCheckCircle)
            ->color($count > 0 ? 'gray' : 'success')
            ->url(LeaveRequestResource::getUrl());
    }

    protected function latestPayrollRun(): Stat
    {
        $run = PayrollRun::query()->latest('period_start')->first();

        if ($run === null) {
            return Stat::make('Payroll', 'No runs yet')
                ->description('Nothing has been run')
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color('gray')
                ->url(PayrollRunResource::getUrl());
        }

        $label = PayrollRun::statusOptions()[$run->status] ?? $run->status;
        $period = "{$run->period_start->format('M j')} – {$run->period_end->format('M j, Y')}";

        return Stat::make('Latest payroll run', $label)
            ->description($period)
            ->descriptionIcon(Heroicon::OutlinedBanknotes)
            ->color(PayrollRun::statusColor($run->status))
            ->url(PayrollRunResource::getUrl('view', ['record' => $run]));
    }

    /** "This period" = the most recently finalized run's period — a draft's figures aren't real yet. */
    protected function pfSsfDue(?PayrollRun $latestFinalizedRun): Stat
    {
        if ($latestFinalizedRun === null) {
            return Stat::make('PF/SSF due', 'No runs yet')
                ->description('Nothing finalized yet')
                ->descriptionIcon(Heroicon::OutlinedBuildingLibrary)
                ->color('gray')
                ->url(PfSsfRemittancePage::getUrl());
        }

        $report = app(PayrollComplianceReportService::class)
            ->pfSsfRemittance($latestFinalizedRun->period_start, $latestFinalizedRun->period_end);

        return Stat::make('PF/SSF due', 'NPR '.number_format($report->grandTotal(), 2))
            ->description('For '.$latestFinalizedRun->period_start->format('M Y'))
            ->descriptionIcon(Heroicon::OutlinedBuildingLibrary)
            ->color('gray')
            ->url(PfSsfRemittancePage::getUrl());
    }

    /** Same "this period" definition as pfSsfDue(). */
    protected function tdsDue(?PayrollRun $latestFinalizedRun): Stat
    {
        if ($latestFinalizedRun === null) {
            return Stat::make('TDS due', 'No runs yet')
                ->description('Nothing finalized yet')
                ->descriptionIcon(Heroicon::OutlinedPercentBadge)
                ->color('gray')
                ->url(TdsReportPage::getUrl());
        }

        $report = app(PayrollComplianceReportService::class)
            ->tds($latestFinalizedRun->period_start, $latestFinalizedRun->period_end);

        return Stat::make('TDS due', 'NPR '.number_format($report->totalTds, 2))
            ->description('For '.$latestFinalizedRun->period_start->format('M Y'))
            ->descriptionIcon(Heroicon::OutlinedPercentBadge)
            ->color('gray')
            ->url(TdsReportPage::getUrl());
    }

    protected function salesThisMonth(VatRegisterReport $vatReport): Stat
    {
        return Stat::make('Sales this month', 'NPR '.number_format($vatReport->totalSales(), 2))
            ->description(Carbon::now()->format('F Y').' so far')
            ->descriptionIcon(Heroicon::OutlinedCreditCard)
            ->color('gray')
            ->url(VatRegisterPage::getUrl());
    }

    protected function vatCollectedThisMonth(VatRegisterReport $vatReport): Stat
    {
        return Stat::make('VAT collected this month', 'NPR '.number_format($vatReport->totalVat, 2))
            ->description(Carbon::now()->format('F Y').' so far')
            ->descriptionIcon(Heroicon::OutlinedDocumentText)
            ->color('gray')
            ->url(VatRegisterPage::getUrl());
    }
}
