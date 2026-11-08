<?php

namespace App\Support;

use App\Domains\Core\Contracts\MenuFilter;
use App\Http\Middleware\EnforceAccess;

/**
 * Leaves out of the menu the screens a floor rule has switched off and the ones the signed-in role holds no permission for, on top of whatever filter is bound (the foundry size).
 * Like any menu filter it only declutters: the screens still open from links.
 */
class RuleMenuFilter implements MenuFilter
{
    public function __construct(private MenuFilter $inner) {}

    public function shows(string $route): bool
    {
        if (config('foundry.menu_basic_only') && ! in_array($route, config('foundry.menu_basic_routes'), true)) {
            return false;
        }

        return ! in_array($route, $this->hidden(), true) && EnforceAccess::menuShows(auth()->user(), $route) && $this->inner->shows($route);
    }

    public function showsAny(array $routes): bool
    {
        foreach ($routes as $route) {
            if ($this->shows($route)) {
                return true;
            }
        }

        return false;
    }

    public function showsModuleGroup(string $key): bool
    {
        return $this->inner->showsModuleGroup($key);
    }

    /** @return list<string> */
    private function hidden(): array
    {
        return array_merge(
            FloorRules::usesWorkLists() ? [] : ['work-lists.index'],
            FloorRules::usesMeltingPlan() ? [] : ['heat-plan.index'],
            FloorRules::usesQuickScreens() ? ['moulding-runs.index', 'mould-readiness.index', 'pours.index', 'work-lists.index', 'heat-plan.index'] : [],
        );
    }
}
