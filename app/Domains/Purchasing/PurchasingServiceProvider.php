<?php

namespace App\Domains\Purchasing;

use App\Domains\Core\ModuleServiceProvider;
use App\Domains\Purchasing\Models\Supplier;

/** The supplier register: approval status, scored evaluations and review dates. Material lots can name the supplier they came from. */
class PurchasingServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'purchasing';
    }

    protected function moduleLabel(): string
    {
        return 'Suppliers';
    }

    protected function moduleDescription(): string
    {
        return 'The approved supplier list with evaluations. Material lots stop offering a supplier to choose.';
    }

    protected function modulePath(): string
    {
        return __DIR__;
    }

    protected function navItems(): array
    {
        return [
            ['group' => 'quality', 'label' => 'Suppliers', 'route' => 'suppliers.index', 'icon' => 'truck', 'active' => ['suppliers.*']],
        ];
    }

    protected function attachables(): array
    {
        return [
            'supplier' => ['label' => 'Supplier', 'model' => Supplier::class, 'edit' => 'quality.manage'],
        ];
    }

    protected function permissionRoutes(): array
    {
        return [
            ['pattern' => 'suppliers.*', 'permission' => 'quality.manage'],
        ];
    }

    protected function codeKinds(): array
    {
        return [
            'supplier' => ['suppliers', 'SUP', 3, 'Supplier'],
        ];
    }
}
