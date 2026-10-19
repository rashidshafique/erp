<?php

namespace App\Domains\Core;

use App\Models\AppSetting;

/**
 * What the installed modules contribute to the shared screens: sidebar links, Settings links and attachable records.
 * Modules fill it from their service provider; the layout and the attachment code read it.
 */
class ModuleRegistry
{
    public const DISABLED_SETTING = 'modules.disabled';

    /** @var array<string, list<array{label: string, route: string, icon: string, active: list<string>}>> */
    private array $navItems = [];

    /** @var array<string, list<array{label: string, route: string, active: list<string>}>> */
    private array $settingsLinks = [];

    /** @var array<string, array{0: string, 1: class-string, 2: string, 3: string|null}> */
    private array $attachables = [];

    /** @var array<string, array{label: string, description: string}> */
    private array $modules = [];

    /** @var list<string>|null */
    private ?array $disabled = null;

    public function addModule(string $name, string $label, string $description): void
    {
        $this->modules[$name] = ['label' => $label, 'description' => $description];
    }

    /** @return array<string, array{label: string, description: string}> */
    public function modules(): array
    {
        return $this->modules;
    }

    /** A module is on unless an administrator switched it off under Settings. */
    public function isEnabled(string $name): bool
    {
        return ! in_array($name, $this->disabledModules(), true);
    }

    /** @return list<string> */
    public function disabledModules(): array
    {
        if ($this->disabled === null) {
            try {
                $stored = json_decode((string) AppSetting::getValue(self::DISABLED_SETTING, '[]'), true);
            } catch (\Throwable) {
                return [];
            }
            $this->disabled = is_array($stored) ? array_values(array_filter($stored, 'is_string')) : [];
        }

        return $this->disabled;
    }

    /** @param list<string> $names */
    public function saveDisabled(array $names): void
    {
        $this->disabled = array_values(array_unique($names));
        AppSetting::setValue(self::DISABLED_SETTING, json_encode($this->disabled), 'Modules switched off');
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
