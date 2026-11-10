<?php

namespace App\Domains\Assets;

use App\Domains\Core\ModuleServiceProvider;

/**
 * Fixed assets (design 28): the register of plant, buildings, vehicles and the tools the foundry owns, each asset's cost and
 * depreciation, and the monthly depreciation run posted to the ledger. Needs the General ledger module to post.
 */
class AssetsServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'assets';
    }

    protected function moduleLabel(): string
    {
        return 'Fixed assets';
    }

    protected function moduleDescription(): string
    {
        return 'The fixed-asset register, asset cards and the monthly depreciation run. Needs the General ledger to post. Switch it off if fixed assets are kept elsewhere; journals already posted stay.';
    }

    protected function modulePath(): string
    {
        return __DIR__;
    }

    protected function permissionCatalog(): array
    {
        return ['assets.manage' => 'Fixed-asset register, depreciation runs and disposals'];
    }

    protected function permissionRoutes(): array
    {
        return [['pattern' => 'assets.*', 'permission' => 'assets.manage']];
    }
}
