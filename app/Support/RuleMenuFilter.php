<?php

namespace App\Support;

use App\Domains\Core\Contracts\MenuFilter;

/**
 * Leaves out of the menu the screens a floor rule has switched off, on top of whatever filter is bound (the foundry size).
 * Like any menu filter it only declutters: the screens still open from links.
 */
class RuleMenuFilter implements MenuFilter
{
    public function __construct(private MenuFilter $inner) {}

    public function shows(string $route): bool
    {
        return ! in_array($route, $this->hidden(), true) && $this->inner->shows($route);
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
