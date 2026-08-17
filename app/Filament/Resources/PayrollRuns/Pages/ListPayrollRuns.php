<?php

namespace App\Filament\Resources\PayrollRuns\Pages;

use App\Filament\Resources\PayrollRuns\PayrollRunResource;
use App\Services\NepaliCalendar;
use App\Services\PayrollRunService;
use Exception;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

class ListPayrollRuns extends ListRecords
{
    protected static string $resource = PayrollRunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->runPayrollAction(),
        ];
    }

    /**
     * A BS year + month picker rather than a free date range — a payroll
     * period is always a whole Nepali calendar month
     * (NepaliCalendar::bsMonthBounds() resolves it to the right AD range,
     * including the exact last day regardless of how many days that BS
     * month has).
     */
    protected function runPayrollAction(): Action
    {
        $currentBsYear = (int) NepaliCalendar::adToBs(now(), 'Y');

        return Action::make('runPayroll')
            ->label('Run Payroll')
            ->icon(Heroicon::OutlinedCalculator)
            // Custom Actions aren't policy-checked automatically the way the
            // stock CreateAction is — see LeaveRequestResource for the same
            // note. Explicit here rather than assumed.
            ->visible(fn (): bool => auth()->user()?->can('Create:PayrollRun') ?? false)
            ->schema([
                Select::make('bs_year')
                    ->label('Year (BS)')
                    ->options(array_combine(
                        range($currentBsYear - 2, $currentBsYear + 1),
                        range($currentBsYear - 2, $currentBsYear + 1),
                    ))
                    ->default($currentBsYear)
                    ->required()
                    ->native(false),
                Select::make('bs_month')
                    ->label('Month (BS)')
                    ->options(NepaliCalendar::bsMonthOptions())
                    ->default((int) NepaliCalendar::adToBs(now(), 'n'))
                    ->required()
                    ->native(false),
            ])
            ->action(function (array $data): void {
                $bounds = NepaliCalendar::bsMonthBounds((int) $data['bs_year'], (int) $data['bs_month']);

                // NepaliCalendar::bsMonthBounds() returns CarbonImmutable
                // (via bsToAd()); PayrollRunService consistently works with
                // the mutable Carbon its own model casts produce (e.g.
                // $run->period_start->copy()) — convert at this boundary
                // rather than widen the service to accept both.
                $periodStart = Carbon::instance($bounds['start_date']);
                $periodEnd = Carbon::instance($bounds['end_date']);

                try {
                    $run = app(PayrollRunService::class)->run($periodStart, $periodEnd, auth()->user());

                    $message = $run->skipped_employees === []
                        ? "{$run->payslips()->count()} payslip(s) calculated."
                        : $run->payslips()->count().' payslip(s) calculated, '.count($run->skipped_employees).' employee(s) skipped — see the run for details.';

                    Notification::make()->title('Payroll run created.')->body($message)->success()->send();

                    $this->redirect(PayrollRunResource::getUrl('view', ['record' => $run]));
                } catch (Exception $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
            });
    }
}
