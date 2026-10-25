<?php

namespace App\Domains\Tooling;

use App\Domains\Core\ModuleServiceProvider;
use App\Domains\Tooling\Models\ToolingLifeLimit;
use App\Domains\Tooling\Models\ToolingMaintenanceRecord;
use App\Domains\Tooling\Models\ToolingUsageRecord;
use App\Domains\Tooling\Services\ToolingLifeStatusService;

/** Tooling maintenance records, service-life limits and usage, and the pages that show a tool and its engraved mark. */
class ToolingServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'tooling';
    }

    protected function moduleLabel(): string
    {
        return 'Tooling maintenance & life';
    }

    protected function moduleDescription(): string
    {
        return 'Tooling maintenance records, service-life limits and tool pages. The pattern and flask pages stop showing life and maintenance.';
    }

    protected function modulePath(): string
    {
        return __DIR__;
    }

    protected function classAliases(): array
    {
        return [
            'App\Models\ToolingLifeLimit' => ToolingLifeLimit::class,
            'App\Models\ToolingMaintenanceRecord' => ToolingMaintenanceRecord::class,
            'App\Models\ToolingUsageRecord' => ToolingUsageRecord::class,
            'App\Services\ToolingLifeStatusService' => ToolingLifeStatusService::class,
        ];
    }

    protected function navItems(): array
    {
        return [
            ['group' => 'equipment', 'label' => 'Cores & core boxes', 'route' => 'cores.index', 'icon' => 'grid', 'active' => ['cores.*', 'core-boxes.*']],
            ['group' => 'equipment', 'label' => 'Maintenance dashboard', 'route' => 'tooling-maintenance.dashboard', 'icon' => 'dashboard', 'active' => ['tooling-maintenance.dashboard']],
            ['group' => 'equipment', 'label' => 'Maintenance records', 'route' => 'tooling-maintenance.index', 'icon' => 'wrench', 'active' => ['tooling-maintenance.index', 'tooling-maintenance.show', 'tooling-maintenance.edit'],
                'count' => fn (): int => ToolingMaintenanceRecord::whereIn('status', ['OPEN', 'IN_PROGRESS'])->count()],
            ['group' => 'equipment', 'label' => 'Tooling life', 'route' => 'tooling-life.dashboard', 'icon' => 'gauge', 'active' => ['tooling-life.*']],
        ];
    }

    protected function attachables(): array
    {
        return [
            'tooling_maintenance' => ['label' => 'Tooling maintenance', 'model' => ToolingMaintenanceRecord::class, 'edit' => 'tooling.manage'],
        ];
    }

    protected function panels(): array
    {
        return ['product.show' => 'tooling::product-cores-panel'];
    }

    protected function permissionRoutes(): array
    {
        return [
            ['pattern' => 'tooling-*', 'permission' => 'tooling.manage'],
            ['pattern' => 'core-requirements.*', 'permission' => 'tooling.manage'],
            ['pattern' => 'cores.*', 'permission' => 'tooling.manage'],
            ['pattern' => 'core-boxes.*', 'permission' => 'tooling.manage'],
            ['pattern' => 'tools.*', 'permission' => 'tooling.manage'],
        ];
    }
}
