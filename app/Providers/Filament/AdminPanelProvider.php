<?php

namespace App\Providers\Filament;

use App\Providers\Filament\Concerns\ConfiguresPanelDefaults;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Icons\Heroicon;

class AdminPanelProvider extends PanelProvider
{
    use ConfiguresPanelDefaults;

    public function panel(Panel $panel): Panel
    {
        return $this->applyPanelDefaults($panel)
            ->default()
            ->id('admin')
            ->path('admin')
            // Declared explicitly so the order is a decision, not the accident
            // of which resource was generated first (DESIGN.md N1/N2). Groups
            // with no resources yet stay silent until their phase lands.
            ->navigationGroups([
                NavigationGroup::make('People')
                    ->icon(Heroicon::OutlinedUsers),
                NavigationGroup::make('Attendance & Leave')
                    ->icon(Heroicon::OutlinedClock),
                NavigationGroup::make('Payroll')
                    ->icon(Heroicon::OutlinedBanknotes),
                NavigationGroup::make('Accounting')
                    ->icon(Heroicon::OutlinedCalculator),
                // Edited rarely, by few people — kept out of the daily-use path.
                NavigationGroup::make('Setup')
                    ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                    ->collapsed(),
                NavigationGroup::make('Administration')
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->collapsed(),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->plugins([
                // Shield ships its own top-level "Filament Shield" group. Our
                // product's navigation shouldn't carry a vendor's brand, so the
                // Roles resource is relabelled into Administration (DESIGN.md N3).
                FilamentShieldPlugin::make()
                    ->navigationGroup('Administration')
                    ->navigationLabel('Roles & Permissions')
                    ->navigationIcon(Heroicon::OutlinedKey)
                    ->navigationSort(20),
            ]);
    }
}
