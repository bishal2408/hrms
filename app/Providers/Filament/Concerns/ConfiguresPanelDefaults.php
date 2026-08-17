<?php

namespace App\Providers\Filament\Concerns;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Configuration shared by every panel — branding, colour, typography and the
 * middleware stack (DESIGN.md P1). The admin and employee panels are the same
 * product and must not drift apart visually, so anything that has to match
 * lives here and is set once.
 *
 * Panel-specific concerns (id, path, discovery paths, navigation groups,
 * plugins) stay in the individual provider.
 */
trait ConfiguresPanelDefaults
{
    protected function applyPanelDefaults(Panel $panel): Panel
    {
        return $panel
            ->login()
            // One primary across both panels. Blue rather than the installer's
            // Amber: amber is our warning colour, and a payroll system whose
            // every button reads as an alert is a payroll system nobody trusts.
            ->colors([
                'primary' => Color::Blue,
            ])
            ->font('Instrument Sans')
            ->brandName(config('app.name'))
            ->favicon(asset('favicon.ico'))
            // Without this, Filament serves its own pre-built CSS, which is
            // scanned from Filament's package source only — raw Tailwind
            // utility classes written in our own Blade views never make it
            // in, no matter how many times `npm run build` runs. This custom
            // theme imports Filament's base theme and adds our app to the
            // scan (resources/css/filament/theme.css).
            ->viteTheme('resources/css/filament/theme.css')
            // No full page reload between screens — this app is almost entirely
            // table and form navigation, where the difference is very visible.
            ->spa()
            ->maxContentWidth(Width::ScreenTwoExtraLarge)
            ->sidebarCollapsibleOnDesktop()
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
