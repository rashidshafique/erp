<?php

namespace App\Domains\Core\Contracts;

/**
 * Which sidebar entries this installation shows. Hiding an entry only declutters the menu: the page still opens by its address
 * and in search, and permissions are unchanged. Core asks only this; where the answer comes from is up to whatever is bound to it.
 */
interface MenuFilter
{
    /** The menu entry that opens this route is shown. */
    public function shows(string $route): bool;

    /** A menu group is shown when any of its entries is. @param list<string> $routes */
    public function showsAny(array $routes): bool;

    /** A module's own menu group, by its registry key, is shown. */
    public function showsModuleGroup(string $key): bool;
}
