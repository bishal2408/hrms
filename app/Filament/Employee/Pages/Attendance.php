<?php

namespace App\Filament\Employee\Pages;

use App\Exceptions\AlreadyClockedInException;
use App\Exceptions\NotClockedInException;
use App\Filament\Employee\Widgets\MyLeaveBalanceWidget;
use App\Filament\Employee\Widgets\MyOverviewWidget;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Services\AttendanceService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use UnitEnum;

/**
 * Employee self-service: clock in, clock out, see recent days.
 *
 * The employee panel's landing page (DESIGN.md E1 — task-first, not
 * CRUD-first): clocking in is the thing an employee does daily, so it is
 * what they see first, with "My profile" one click away in the nav.
 *
 * All writes go through AttendanceService, which owns the one-row-per-day
 * state machine. This page never touches AttendanceLog::create()/update()
 * directly — see the service for why.
 */
class Attendance extends Page
{
    protected string $view = 'filament.employee.pages.attendance';

    protected static ?string $slug = 'attendance';

    protected static ?string $title = 'Attendance';

    protected static ?string $navigationLabel = 'Attendance';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = 'Attendance & Leave';

    protected static ?int $navigationSort = 10;

    /**
     * The self-service half of DESIGN.md D2 (my pending requests, my latest
     * payslip, my leave balance) — shown above this page's own clock in/out
     * content, which already covers D2's "clock in/out state".
     *
     * @return array<class-string<Widget>>
     */
    protected function getHeaderWidgets(): array
    {
        return [
            MyOverviewWidget::class,
            MyLeaveBalanceWidget::class,
        ];
    }

    /** The signed-in user's own employee record, or null when none is linked. */
    public function getEmployee(): ?Employee
    {
        return auth()->user()?->employee;
    }

    /** Today's record, if one exists yet. */
    public function getTodayLog(): ?AttendanceLog
    {
        $employee = $this->getEmployee();

        if ($employee === null) {
            return null;
        }

        return AttendanceLog::query()
            ->forEmployee($employee)
            ->onDate(Carbon::now()->toDateString())
            ->first();
    }

    /** @return Collection<int, AttendanceLog> */
    public function getRecentLogs(): Collection
    {
        $employee = $this->getEmployee();

        if ($employee === null) {
            return new Collection;
        }

        return AttendanceLog::query()
            ->forEmployee($employee)
            ->orderByDesc('date')
            ->limit(14)
            ->get();
    }

    public function clockIn(): void
    {
        $employee = $this->getEmployee();

        if ($employee === null) {
            return;
        }

        try {
            app(AttendanceService::class)->clockIn($employee);

            Notification::make()->title('Clocked in.')->success()->send();
        } catch (AlreadyClockedInException $e) {
            Notification::make()->title($e->getMessage())->warning()->send();
        }
    }

    public function clockOut(): void
    {
        $employee = $this->getEmployee();

        if ($employee === null) {
            return;
        }

        try {
            app(AttendanceService::class)->clockOut($employee);

            Notification::make()->title('Clocked out.')->success()->send();
        } catch (NotClockedInException $e) {
            Notification::make()->title($e->getMessage())->warning()->send();
        }
    }
}
