<?php

namespace App\Domains\Core;

use App\Domains\Core\Contracts\ModuleAccess;

/** The default: every module is licensed and on. Used when nothing else is bound, for example when the Licensing unit is removed. */
class OpenModuleAccess implements ModuleAccess
{
    public function isEnabled(string $name): bool
    {
        return true;
    }

    public function isLicensed(string $name): bool
    {
        return true;
    }
}
