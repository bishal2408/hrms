<?php

namespace App\Providers\Filament;

use App\Providers\Filament\Concerns\ConfiguresPanelDefaults;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;

class EmployeePanelProvider extends PanelProvider
{
    use ConfiguresPanelDefaults;

    public function panel(Panel $panel): Panel
    {
        return $this->applyPanelDefaults($panel)
            ->id('employee')
            ->path('employee')
            ->discoverResources(in: app_path('Filament/Employee/Resources'), for: 'App\Filament\Employee\Resources')
            ->discoverPages(in: app_path('Filament/Employee/Pages'), for: 'App\Filament\Employee\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Employee/Widgets'), for: 'App\Filament\Employee\Widgets');
    }
}
