<?php

namespace App\Domains\Core;

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
}
