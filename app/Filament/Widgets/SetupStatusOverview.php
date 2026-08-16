<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\LeaveTypes\LeaveTypeResource;
use App\Filament\Resources\PayrollRates\PayrollRateResource;
use App\Filament\Resources\TaxSlabs\TaxSlabResource;
use App\Models\LeaveType;
use App\Models\PayrollRate;
use App\Models\TaxSlab;
use App\Services\NepaliCalendar;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * What an HR admin needs to know at the front door *today*.
 *
 * Right now that is the configuration state: employees, attendance, leave
 * requests and payroll runs do not exist until their phases land, so this
 * widget reports on the data the app actually has rather than showing
 * invented headcount figures. Each stat links to the screen that fixes it.
 *
 * Replace/extend these stats as real operational data arrives (DESIGN.md D2).
 */
class SetupStatusOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            $this->fiscalYear(),
            $this->contributionRates(),
            $this->taxSlabs(),
            $this->leaveTypes(),
        ];
    }

    /**
     * Orientation, not a task: which Nepali fiscal year payroll periods, leave
     * resets and VAT periods currently key off.
     */
    protected function fiscalYear(): Stat
    {
        $fiscalYear = NepaliCalendar::currentFiscalYear();

        return Stat::make('Fiscal year', $fiscalYear['label'])
            ->description('Today is '.NepaliCalendar::adToBs(now()).' BS')
            ->descriptionIcon(Heroicon::OutlinedCalendar)
            ->color('gray');
    }

    /** Both statutory funds need a currently-effective rate before payroll can run. */
    protected function contributionRates(): Stat
    {
        $configured = collect(array_keys(PayrollRate::typeOptions()))
            ->filter(fn (string $type): bool => PayrollRate::currentFor($type) !== null)
            ->count();

        $total = count(PayrollRate::typeOptions());
        $isComplete = $configured === $total;

        return Stat::make('PF & SSF rates', "{$configured} of {$total}")
            ->description($isComplete ? 'Both funds have a current rate' : 'A fund has no effective rate')
            ->descriptionIcon($isComplete ? Heroicon::OutlinedCheckCircle : Heroicon::OutlinedExclamationTriangle)
            ->color($isComplete ? 'success' : 'warning')
            ->url(PayrollRateResource::getUrl());
    }

    /** TDS needs a slab table for every marital status. */
    protected function taxSlabs(): Stat
    {
        $brackets = collect(array_keys(TaxSlab::maritalStatusOptions()))
            ->map(fn (string $status): int => TaxSlab::slabsFor($status)->count());

        $total = $brackets->sum();
        $isComplete = $brackets->every(fn (int $count): bool => $count > 0);

        return Stat::make('Tax brackets', (string) $total)
            ->description($isComplete ? 'Every marital status has a slab table' : 'A marital status has no slab table')
            ->descriptionIcon($isComplete ? Heroicon::OutlinedCheckCircle : Heroicon::OutlinedExclamationTriangle)
            ->color($isComplete ? 'success' : 'warning')
            ->url(TaxSlabResource::getUrl());
    }

    /** Leave requests in Phase 2 have nothing to request against without these. */
    protected function leaveTypes(): Stat
    {
        $count = LeaveType::query()->count();

        return Stat::make('Leave types', (string) $count)
            ->description($count > 0 ? 'Configured and available' : 'None configured yet')
            ->descriptionIcon($count > 0 ? Heroicon::OutlinedCheckCircle : Heroicon::OutlinedExclamationTriangle)
            ->color($count > 0 ? 'success' : 'warning')
            ->url(LeaveTypeResource::getUrl());
    }
}
