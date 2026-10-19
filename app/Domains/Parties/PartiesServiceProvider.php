<?php

namespace App\Domains\Parties;

use App\Domains\Core\ModuleServiceProvider;
use App\Domains\Parties\Models\Party;

/** Business partners: one record per company, with customer and supplier as the roles it holds. */
class PartiesServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'parties';
    }

    protected function moduleLabel(): string
    {
        return 'Business partners';
    }

    protected function moduleDescription(): string
    {
        return 'The shared register behind customers and suppliers. Switching it off hides the partner screens; customers and suppliers keep working.';
    }

    protected function modulePath(): string
    {
        return __DIR__;
    }

    protected function navItems(): array
    {
        return [
            ['group' => 'sales', 'label' => 'Business partners', 'route' => 'parties.index', 'icon' => 'users', 'active' => ['parties.*']],
        ];
    }

    protected function attachables(): array
    {
        return [
            'party' => ['label' => 'Business partner', 'model' => Party::class, 'edit' => 'quality.manage'],
        ];
    }

    protected function permissionRoutes(): array
    {
        return [
            ['pattern' => 'parties.*', 'permission' => 'quality.manage'],
        ];
    }

    protected function codeKinds(): array
    {
        return [
            'party' => ['parties', 'BP', 7, 'Business partner'],
        ];
    }
}
