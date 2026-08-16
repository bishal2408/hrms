<?php

namespace App\Providers;

use App\Policies\RolePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Role lives outside app/Models, so Laravel's policy auto-discovery
        // (App\Policies\{Model}Policy convention) can't find RolePolicy on
        // its own — Shield's generator flags this as "requires registration".
        Gate::policy(Role::class, RolePolicy::class);
    }
}
