<?php

namespace App\Domains\Licensing;

use App\Domains\Core\Contracts\ModuleAccess;
use App\Domains\Core\ModuleRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * Which modules are licensed and switched on, and the Settings page that shows it.
 * Not a module itself: it cannot be switched off. Removing this folder and its entry in bootstrap/providers.php
 * leaves every module on, because core falls back to OpenModuleAccess.
 */
class LicensingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingsModuleAccess::class);
        $this->app->singleton(ModuleAccess::class, fn () => $this->app->make(SettingsModuleAccess::class));
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/resources/views', 'licensing');
        if (! $this->app->routesAreCached()) {
            $this->loadRoutesFrom(__DIR__.'/routes.php');
        }

        $this->app->make(ModuleRegistry::class)->addSettingsLink('Access & data', [
            'label' => 'Modules', 'route' => 'settings.modules.edit', 'active' => ['settings.modules*'],
        ]);
    }
}
