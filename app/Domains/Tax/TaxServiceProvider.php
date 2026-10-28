<?php

namespace App\Domains\Tax;

use App\Domains\Core\Contracts\TaxCalculator;
use App\Domains\Core\ModuleRegistry;
use App\Domains\Core\ModuleServiceProvider;
use App\Domains\Core\Tax\TaxJurisdictions;
use App\Domains\Tax\Jurisdictions\Pakistan;
use App\Domains\Tax\Services\RuleBasedTax;

/**
 * Taxation: each country is a pack of authorities, dated tax rates and rules, kept as data the accounts team edits. Without this
 * module, invoices, bills and orders tax each line once at the percent chosen on it.
 */
class TaxServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'tax';
    }

    protected function moduleLabel(): string
    {
        return 'Taxation';
    }

    protected function moduleDescription(): string
    {
        return 'Tax by country rules: several taxes per line (goods and services, federal and provincial), withholding, and registration numbers on customers and suppliers. Switching it off goes back to one rate per line.';
    }

    protected function modulePath(): string
    {
        return __DIR__;
    }

    public function boot(): void
    {
        parent::boot();
        // Country packs are known even with the module off, so the tariff code keeps its country's format.
        $this->app->make(TaxJurisdictions::class)->register('PK', Pakistan::class);
        if ($this->app->make(ModuleRegistry::class)->isEnabled('tax')) {
            $this->app->singleton(TaxCalculator::class, RuleBasedTax::class);
        }
    }

    protected function settingsLinks(): array
    {
        return [];   // Taxation sits on Finance > Tax & year end
    }

    protected function permissionCatalog(): array
    {
        return ['tax.manage' => 'Tax rates, rules and the tax details of customers and suppliers'];
    }

    protected function permissionRoutes(): array
    {
        return [['pattern' => 'tax.*', 'permission' => 'tax.manage']];
    }

    protected function panels(): array
    {
        return ['party.show' => 'tax::party-panel', 'finance.tax-year' => 'tax::settings.panel'];
    }
}
