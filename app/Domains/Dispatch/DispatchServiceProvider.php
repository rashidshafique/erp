<?php

namespace App\Domains\Dispatch;

use App\Domains\Core\ModuleServiceProvider;
use App\Domains\Dispatch\Models\Dispatch;
use App\Domains\Dispatch\Models\DispatchItem;
use App\Domains\Dispatch\Models\PackingRecord;
use App\Domains\Dispatch\Models\PackingRecordItem;
use App\Domains\Dispatch\Services\ShipmentService;

/** Packing, finished goods and dispatch: from an accepted casting lot to the delivery challan. */
class DispatchServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'dispatch';
    }

    protected function moduleLabel(): string
    {
        return 'Packing & dispatch';
    }

    protected function moduleDescription(): string
    {
        return 'Packing records, finished goods and dispatches. The production queue stops counting castings awaiting dispatch.';
    }

    protected function modulePath(): string
    {
        return __DIR__;
    }

    protected function classAliases(): array
    {
        return [
            'App\Models\Dispatch' => Dispatch::class,
            'App\Models\DispatchItem' => DispatchItem::class,
            'App\Models\PackingRecord' => PackingRecord::class,
            'App\Models\PackingRecordItem' => PackingRecordItem::class,
            'App\Services\Production\ShipmentService' => ShipmentService::class,
        ];
    }

    /** Finished goods, packing and dispatch have a menu of their own, after Quality. */
    protected function navGroups(): array
    {
        return [[
            'key' => 'dispatch', 'label' => 'Dispatch', 'icon' => 'truck', 'active' => ['finished-goods.*', 'packing.*', 'dispatches.*'],
            'items' => [
                ['label' => 'Finished goods', 'route' => 'finished-goods.index', 'icon' => 'archive', 'active' => ['finished-goods.*']],
                ['label' => 'Packing', 'route' => 'packing.index', 'icon' => 'package', 'active' => ['packing.*']],
                ['label' => 'Dispatch', 'route' => 'dispatches.index', 'icon' => 'truck', 'active' => ['dispatches.*']],
            ],
        ]];
    }

    protected function permissionCatalog(): array
    {
        return [
            'packing.operate' => 'Packing',
            'dispatch.operate' => 'Dispatch',
        ];
    }

    protected function permissionRoutes(): array
    {
        return [
            ['pattern' => 'packing.*', 'permission' => 'packing.operate'],
            ['pattern' => 'dispatches.*', 'permission' => 'dispatch.operate'],
        ];
    }

    protected function approvalActions(): array
    {
        return [
            'dispatch.create' => 'Dispatch castings to a customer',
        ];
    }

    protected function attachables(): array
    {
        return [
            'packing' => ['label' => 'Packing record', 'model' => PackingRecord::class, 'edit' => 'packing.operate'],
            'dispatch' => ['label' => 'Dispatch', 'model' => Dispatch::class, 'edit' => 'dispatch.operate'],
        ];
    }
}
