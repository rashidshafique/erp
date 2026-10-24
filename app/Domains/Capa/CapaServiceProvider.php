<?php

namespace App\Domains\Capa;

use App\Domains\Capa\Models\CapaAction;
use App\Domains\Core\ModuleServiceProvider;

/** The corrective action register: root cause, action and effectiveness check, raised from NCRs or any other source. */
class CapaServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'capa';
    }

    protected function moduleLabel(): string
    {
        return 'Corrective actions (CAPA)';
    }

    protected function moduleDescription(): string
    {
        return 'The corrective action register. Nonconformance reports stop offering to raise an action.';
    }

    protected function modulePath(): string
    {
        return __DIR__;
    }

    protected function classAliases(): array
    {
        return [
            'App\Models\CapaAction' => CapaAction::class,
        ];
    }

    protected function navItems(): array
    {
        return [
            ['group' => 'quality', 'label' => 'Corrective actions', 'route' => 'capa.index', 'icon' => 'check-circle', 'active' => ['capa.*'],
                'count' => fn (): int => CapaAction::whereIn('status', ['OPEN', 'IN_PROGRESS', 'AWAITING_VERIFICATION'])->count()],
        ];
    }

    protected function attachables(): array
    {
        return [
            'capa' => ['label' => 'Corrective action', 'model' => CapaAction::class, 'edit' => 'quality.manage'],
        ];
    }

    protected function permissionRoutes(): array
    {
        return [
            ['pattern' => 'capa.*', 'permission' => 'quality.manage'],
        ];
    }

    protected function approvalActions(): array
    {
        return [
            'capa.close' => 'Close a corrective action',
        ];
    }

    protected function codeKinds(): array
    {
        return [
            'capa' => ['capa_actions', 'CPA', 4, 'Corrective action'],
        ];
    }
}
