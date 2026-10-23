<?php

namespace App\Domains\Inventory;

use App\Domains\Core\ModuleServiceProvider;
use Livewire\Livewire;

/** Stores: where stock is kept. Stock locations, then movements and balances by location. */
class InventoryServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'inventory';
    }

    protected function moduleLabel(): string
    {
        return 'Stores';
    }

    protected function moduleDescription(): string
    {
        return 'Stock locations (stores, yards, bins), stock by location, and screens to issue, scrap, adjust and transfer stock. Materials and the stock report stay.';
    }

    protected function modulePath(): string
    {
        return __DIR__;
    }

    public function boot(): void
    {
        parent::boot();

        Livewire::addNamespace('inventory', viewPath: __DIR__.'/resources/views/livewire');
    }

    protected function navItems(): array
    {
        return [
            ['group' => 'melting', 'label' => 'Stock by location', 'route' => 'stock-balances.index', 'icon' => 'package', 'active' => ['stock-balances.*', 'stock-movements.record']],
            ['group' => 'melting', 'label' => 'Stock locations', 'route' => 'stock-locations.index', 'icon' => 'package', 'active' => ['stock-locations.*']],
        ];
    }
}
