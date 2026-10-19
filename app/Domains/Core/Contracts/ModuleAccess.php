<?php

namespace App\Domains\Core\Contracts;

/**
 * Who decides which modules this installation may use and has switched on.
 * Core and every module ask only this; where the answer comes from is up to whatever is bound to it.
 */
interface ModuleAccess
{
    /** The module is licensed and switched on. */
    public function isEnabled(string $name): bool;

    /** The installation is entitled to the module, whether or not it is switched on. */
    public function isLicensed(string $name): bool;
}
