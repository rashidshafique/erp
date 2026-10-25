<?php

namespace App\Domains\Engineering;

use App\Domains\Core\ModuleServiceProvider;

/** The bill of materials: the items a product revision uses besides metal, and what a production order needs of them. */
class EngineeringServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'engineering';
    }

    protected function moduleLabel(): string
    {
        return 'Bill of materials';
    }

    protected function moduleDescription(): string
    {
        return 'The item list of each product revision and the need it gives a production order. The product and production order pages stop showing it.';
    }

    protected function modulePath(): string
    {
        return __DIR__;
    }

    protected function permissionRoutes(): array
    {
        return [['pattern' => 'bom.*', 'permission' => 'master.manage']];
    }

    protected function panels(): array
    {
        return [
            'product.show' => 'engineering::product-panel',
            'production-order.show' => 'engineering::order-panel',
        ];
    }
}
