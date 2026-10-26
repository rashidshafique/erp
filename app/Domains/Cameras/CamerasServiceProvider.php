<?php

namespace App\Domains\Cameras;

use App\Domains\Core\ModuleServiceProvider;

/** Live pictures from the plant's cameras, read from a Dahua recorder, with a page to set the recorder and its channels up. */
class CamerasServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'cameras';
    }

    protected function moduleLabel(): string
    {
        return 'Cameras';
    }

    protected function moduleDescription(): string
    {
        return 'The live camera page and the recorder (DVR) and camera setup. The pictures are read by the app from a Dahua recorder; the recorder password never reaches the browser.';
    }

    protected function modulePath(): string
    {
        return __DIR__;
    }

    protected function navGroups(): array
    {
        return [[
            'key' => 'cameras', 'label' => 'Cameras', 'icon' => 'camera', 'active' => ['cameras.*'],
            'items' => [
                ['label' => 'Live view', 'route' => 'cameras.index', 'icon' => 'monitor', 'active' => ['cameras.index', 'cameras.snapshot', 'cameras.stream']],
                ['label' => 'Camera setup', 'route' => 'cameras.setup', 'icon' => 'sliders', 'active' => ['cameras.setup', 'cameras.dvrs.*', 'cameras.channels.*', 'cameras.update', 'cameras.destroy']],
            ],
        ]];
    }

    protected function permissionCatalog(): array
    {
        return [
            'cameras.view' => 'View live camera pictures',
            'cameras.manage' => 'Set up the recorder and cameras',
        ];
    }

    protected function permissionRoutes(): array
    {
        return [
            ['pattern' => 'cameras.setup', 'permission' => 'cameras.manage', 'reads' => true],
            ['pattern' => 'cameras.dvrs.*', 'permission' => 'cameras.manage', 'reads' => true],
            ['pattern' => 'cameras.channels.*', 'permission' => 'cameras.manage', 'reads' => true],
            ['pattern' => 'cameras.update', 'permission' => 'cameras.manage', 'reads' => true],
            ['pattern' => 'cameras.destroy', 'permission' => 'cameras.manage', 'reads' => true],
            ['pattern' => 'cameras.*', 'permission' => 'cameras.view', 'reads' => true],
        ];
    }
}
