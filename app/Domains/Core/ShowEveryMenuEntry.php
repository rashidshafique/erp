<?php

namespace App\Domains\Core;

use App\Domains\Core\Contracts\MenuFilter;

/** The default: every menu entry is shown. Used when nothing else is bound, for example when the Licensing unit is removed. */
class ShowEveryMenuEntry implements MenuFilter
{
    public function shows(string $route): bool
    {
        return true;
    }

    public function showsAny(array $routes): bool
    {
        return true;
    }

    public function showsModuleGroup(string $key): bool
    {
        return true;
    }
}
