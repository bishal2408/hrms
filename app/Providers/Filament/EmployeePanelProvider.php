<?php

namespace App\Providers\Filament;

use App\Filament\Employee\Pages\Attendance;
use App\Filament\Employee\Pages\MyProfile;
use App\Providers\Filament\Concerns\ConfiguresPanelDefaults;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Icons\Heroicon;

class EmployeePanelProvider extends PanelProvider
{
    use ConfiguresPanelDefaults;

    public function panel(Panel $panel): Panel
    {
        return $this->applyPanelDefaults($panel)
            ->id('employee')
            ->path('employee')
            // Same rule as admin (DESIGN.md N1): no root-level orphans. None of
            // these are ->collapsed() — this is still a small enough nav that
            // admin's low-frequency-group reasoning (Setup/Administration)
            // doesn't apply.
            ->navigationGroups([
                NavigationGroup::make('Attendance & Leave')
                    ->icon(Heroicon::OutlinedClock),
                NavigationGroup::make('Payroll')
                    ->icon(Heroicon::OutlinedBanknotes),
                NavigationGroup::make('Account')
                    ->icon(Heroicon::OutlinedUserCircle),
            ])
            ->discoverResources(in: app_path('Filament/Employee/Resources'), for: 'App\Filament\Employee\Resources')
            ->discoverPages(in: app_path('Filament/Employee/Pages'), for: 'App\Filament\Employee\Pages')
            // No Dashboard — clocking in is the daily task, so it is what
            // employees land on (DESIGN.md E1: task-first, not CRUD-first).
            // "My profile" stays one click away in the nav.
            ->pages([
                Attendance::class,
                MyProfile::class,
            ])
            ->homeUrl(fn (): string => Attendance::getUrl(panel: 'employee'))
            ->discoverWidgets(in: app_path('Filament/Employee/Widgets'), for: 'App\Filament\Employee\Widgets');
    }
}
