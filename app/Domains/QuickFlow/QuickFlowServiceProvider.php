<?php

namespace App\Domains\QuickFlow;

use App\Domains\Core\ModuleServiceProvider;

/** A short path for a small foundry: enter an order, type in moulds made and poured, and see what the next heat should melt. It sits on top of the full records and does not replace them. */
class QuickFlowServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'quickflow';
    }

    protected function moduleLabel(): string
    {
        return 'Quick flow';
    }

    protected function moduleDescription(): string
    {
        return 'The quick order, quick floor entry and next heat pages. The full order, moulding and pouring records stay.';
    }

    protected function modulePath(): string
    {
        return __DIR__;
    }

    protected function navGroups(): array
    {
        return [[
            'key' => 'quickflow', 'label' => 'Quick flow', 'icon' => 'clipboard', 'active' => ['quick.*'],
            'items' => [
                ['label' => 'Next heat', 'route' => 'quick.heat.index', 'icon' => 'calendar', 'active' => ['quick.heat.*']],
                ['label' => 'Moulds made / poured', 'route' => 'quick.floor.index', 'icon' => 'clipboard', 'active' => ['quick.floor.*']],
                ['label' => 'New order', 'route' => 'quick.orders.create', 'icon' => 'clipboard', 'active' => ['quick.orders.*']],
            ],
        ]];
    }

    protected function permissionRoutes(): array
    {
        return [
            ['pattern' => 'quick.orders.*', 'permission' => 'sales.manage'],
            ['pattern' => 'quick.heat.*', 'permission' => 'planning.manage'],
            ['pattern' => 'quick.floor.*', 'permission' => 'shopfloor.record'],
        ];
    }
}
