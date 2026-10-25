<?php

namespace App\Domains\Core;

use App\Domains\Core\Contracts\ModuleAccess;

/**
 * What the installed modules contribute to the shared screens: sidebar links, Settings links and attachable records.
 * Modules fill it from their service provider; the layout and the attachment code read it.
 */
class ModuleRegistry
{
    /** @var array<string, list<array{label: string, route: string, icon: string, active: list<string>}>> */
    private array $navItems = [];

    /** @var array<string, list<array{label: string, route: string, active: list<string>}>> */
    private array $settingsLinks = [];

    /** @var array<string, array{0: string, 1: class-string, 2: string, 3: string|null}> */
    private array $attachables = [];

    /** @var array<string, array{label: string, description: string}> */
    private array $modules = [];

    /** @var array<string, array<string, mixed>> */
    private array $navGroups = [];

    /** @var array<string, array<string, mixed>> */
    private array $searchTypes = [];

    /** @var array<string, array{0: string, 1: string, 2: int, 3: string}> */
    private array $codeKinds = [];

    /** @var array<string, list<string>> */
    private array $panels = [];

    public function __construct(private ModuleAccess $access) {}

    public function addModule(string $name, string $label, string $description): void
    {
        $this->modules[$name] = ['label' => $label, 'description' => $description];
    }

    /** @return array<string, array{label: string, description: string}> */
    public function modules(): array
    {
        return $this->modules;
    }

    /** Whether the module is switched on for this installation. The answer comes from whatever is bound as ModuleAccess. */
    public function isEnabled(string $name): bool
    {
        return $this->access->isEnabled($name);
    }

    /** @param array{label: string, route: string, icon: string, active: list<string>} $item */
    public function addNavItem(string $group, array $item): void
    {
        $this->navItems[$group][] = $item;
    }

    /** @return list<array{label: string, route: string, icon: string, active: list<string>}> */
    public function navItems(string $group): array
    {
        return $this->navItems[$group] ?? [];
    }

    /** How many things wait on someone in this link, from its optional 'count' closure. */
    public function navCount(array $item): int
    {
        return isset($item['count']) ? (int) ($item['count'])() : 0;
    }

    /**
     * The waiting count of the links the menu shows, so the group holding them can carry it too.
     *
     * @param  list<array<string, mixed>>  $items
     */
    public function groupCount(array $items, Contracts\MenuFilter $menu): int
    {
        return array_sum(array_map(fn ($item) => $menu->shows($item['route']) && (! isset($item['visible']) || ($item['visible'])()) ? $this->navCount($item) : 0, $items));
    }

    /** Every route pattern the group's contributed links answer to, so the group opens on those pages. @return list<string> */
    public function navPatterns(string $group): array
    {
        return array_merge([], ...array_column($this->navItems($group), 'active'));
    }

    /** @param array{label: string, route: string, active: list<string>} $link */
    public function addSettingsLink(string $section, array $link): void
    {
        $this->settingsLinks[$section][] = $link;
    }

    /** @return array<string, list<array{label: string, route: string, active: list<string>}>> */
    public function settingsLinks(): array
    {
        return $this->settingsLinks;
    }

    /** A record that can carry files: its label, model, the permission to add files and the permission to read them (null when any signed-in user may). */
    public function addAttachable(string $key, string $label, string $model, string $editPermission, ?string $viewPermission = null): void
    {
        $this->attachables[$key] = [$label, $model, $editPermission, $viewPermission];
    }

    /** @return array<string, array{0: string, 1: class-string, 2: string, 3: string|null}> */
    public function attachables(): array
    {
        return $this->attachables;
    }

    /**
     * A whole sidebar group of its own. Items may carry a `visible` closure, checked each time the menu is drawn.
     *
     * @param  array{key: string, label: string, icon: string, active?: list<string>, items: list<array{label: string, route: string, icon: string, active: list<string>, visible?: \Closure|null}>}  $group
     */
    public function addNavGroup(array $group): void
    {
        $this->navGroups[$group['key']] = $group;
    }

    /** @return list<array{key: string, label: string, icon: string, active: list<string>, items: list<array<string, mixed>>}> */
    public function navGroups(string $position = 'bottom'): array
    {
        return array_values(array_map(
            fn (array $g) => $g + ['active' => []],
            array_filter($this->navGroups, fn (array $g) => ($g['position'] ?? 'bottom') === $position),
        ));
    }

    /** @param array<string, array<string, mixed>> $types Same shape as the core GlobalSearch types. */
    public function addSearchTypes(array $types): void
    {
        $this->searchTypes = $types + $this->searchTypes;
    }

    /** @return array<string, array<string, mixed>> */
    public function searchTypes(): array
    {
        return $this->searchTypes;
    }

    /** @param array<string, array{0: string, 1: string, 2: int, 3: string}> $kinds Same shape as the core CodeGenerator kinds. */
    public function addCodeKinds(array $kinds): void
    {
        $this->codeKinds = $kinds + $this->codeKinds;
    }

    /** @return array<string, array{0: string, 1: string, 2: int, 3: string}> */
    public function codeKinds(): array
    {
        return $this->codeKinds;
    }

    /** Adds a view a module shows on a core page, at the named place on it (such as `party.show`). */
    public function addPanel(string $slot, string $view): void
    {
        $this->panels[$slot][] = $view;
    }

    /**
     * The module views to show at a place on a core page; the page includes each with its own data.
     *
     * @return list<string>
     */
    public function panels(string $slot): array
    {
        return $this->panels[$slot] ?? [];
    }
}
