<?php

namespace App\Domains\Purchasing;

use App\Domains\Core\ModuleServiceProvider;
use App\Domains\Purchasing\Models\PurchaseOrder;
use App\Domains\Purchasing\Models\Supplier;
use App\Models\MaterialLot;
use Livewire\Livewire;

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
        return 'The approved supplier list with evaluations, and purchase orders with receiving into material lots. Material lots stop offering a supplier to choose.';
    }

    protected function modulePath(): string
    {
        return __DIR__;
    }

    public function boot(): void
    {
        parent::boot();

        Livewire::addNamespace('purchasing', viewPath: __DIR__.'/resources/views/livewire');
    }

    protected function navItems(): array
    {
        return [
            ['group' => 'purchases', 'label' => 'Suppliers', 'route' => 'suppliers.index', 'icon' => 'store', 'active' => ['suppliers.*']],
            ['group' => 'purchases', 'label' => 'Purchase orders', 'route' => 'purchase-orders.index', 'icon' => 'clipboard', 'active' => ['purchase-orders.*'],
                'count' => fn (): int => PurchaseOrder::where('status', 'DRAFT')->count()],
        ];
    }

    protected function attachables(): array
    {
        return [
            'supplier' => ['label' => 'Supplier', 'model' => Supplier::class, 'edit' => 'quality.manage'],
            'purchase_order' => ['label' => 'Purchase order', 'model' => PurchaseOrder::class, 'edit' => 'purchasing.manage'],
            'material_lot' => ['label' => 'Material lot', 'model' => MaterialLot::class, 'edit' => 'quality.manage'],
        ];
    }

    protected function permissionRoutes(): array
    {
        return [
            ['pattern' => 'suppliers.*', 'permission' => 'quality.manage'],
            ['pattern' => 'incoming.*', 'permission' => 'quality.manage'],
            ['pattern' => 'purchase-orders.*', 'permission' => 'purchasing.manage'],
        ];
    }

    protected function permissionCatalog(): array
    {
        return ['purchasing.manage' => 'Purchase orders and receiving'];
    }

    protected function roleDefaults(): array
    {
        return ['BUYER' => ['name' => 'Buyer', 'permissions' => ['purchasing.manage', 'attachments.manage']]];
    }

    protected function approvalActions(): array
    {
        return ['purchase_order.issue' => 'Issue a purchase order'];
    }

    protected function settingsLinks(): array
    {
        return [['section' => 'Foundry', 'label' => 'Purchase orders', 'route' => 'purchase-orders.settings.edit', 'active' => ['purchase-orders.settings.*']]];
    }

    protected function codeKinds(): array
    {
        return [
            'supplier' => ['suppliers', 'SUP', 3, 'Supplier'],
        ];
    }
}
