<?php

namespace App\Domains\Core;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

/**
 * Base for a domain module: a self-contained folder under app/Domains holding its own routes, views and migrations.
 * A module extends this, names itself, and is listed in bootstrap/providers.php.
 */
abstract class ModuleServiceProvider extends ServiceProvider
{
    /** The module name, lower-cased, used as the view namespace: `maintenance::gauges.index`. */
    abstract protected function moduleName(): string;

    /** The name shown under Settings > Modules. */
    protected function moduleLabel(): string
    {
        return ucfirst($this->moduleName());
    }

    /** One line saying what switching the module off removes. */
    protected function moduleDescription(): string
    {
        return '';
    }

    /** The absolute path of the module folder. */
    abstract protected function modulePath(): string;

    /**
     * Old class names that still resolve to a class that has moved into the module.
     *
     * @return array<string, class-string>
     */
    protected function classAliases(): array
    {
        return [];
    }

    /**
     * Links this module adds to the sidebar group of that name.
     *
     * @return list<array{group: string, label: string, route: string, icon: string, active: list<string>}>
     */
    protected function navItems(): array
    {
        return [];
    }

    /**
     * Links this module adds to the Settings column, under a section heading.
     *
     * @return list<array{section: string, label: string, route: string, active: list<string>}>
     */
    protected function settingsLinks(): array
    {
        return [];
    }

    /**
     * Records of this module that can carry files, keyed by the name stored with the attachment.
     *
     * @return array<string, array{label: string, model: class-string, edit: string, view?: string|null}>
     */
    protected function attachables(): array
    {
        return [];
    }

    /**
     * Route-name patterns this module puts behind a permission, as in config/permissions.php.
     *
     * @return list<array{pattern: string, permission: string, reads?: bool}>
     */
    protected function permissionRoutes(): array
    {
        return [];
    }

    /**
     * Whole sidebar groups this module adds. Each item may carry a `visible` closure.
     *
     * @return list<array{key: string, label: string, icon: string, active?: list<string>, items: list<array{label: string, route: string, icon: string, active: list<string>, visible?: \Closure|null}>}>
     */
    protected function navGroups(): array
    {
        return [];
    }

    /**
     * Permissions this module adds to the Roles screen, as key => label.
     *
     * @return array<string, string>
     */
    protected function permissionCatalog(): array
    {
        return [];
    }

    /**
     * Route-name patterns open to every signed-in user, as in config/permissions.php.
     *
     * @return list<string>
     */
    protected function openRoutes(): array
    {
        return [];
    }

    /**
     * Starting roles this module adds, as code => [name, permissions]. Registered even when the module is off,
     * because roles are written to the database by migrations.
     *
     * @return array<string, array{name: string, permissions: list<string>}>
     */
    protected function roleDefaults(): array
    {
        return [];
    }

    /**
     * Actions this module lets an administrator put behind an approval, as key => label.
     *
     * @return array<string, string>
     */
    protected function approvalActions(): array
    {
        return [];
    }

    /**
     * Artisan commands this module provides.
     *
     * @return list<class-string>
     */
    protected function consoleCommands(): array
    {
        return [];
    }

    /** Scheduled work this module needs. */
    protected function schedule(Schedule $schedule): void {}

    /**
     * Record types the top-bar search finds, in the shape of GlobalSearch's core types.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function searchTypes(): array
    {
        return [];
    }

    /**
     * Item-code kinds this module issues, in the shape of CodeGenerator's core kinds.
     *
     * @return array<string, array{0: string, 1: string, 2: int, 3: string}>
     */
    protected function codeKinds(): array
    {
        return [];
    }

    /**
     * Views this module shows on core pages, as place => view; the page includes each with its own data.
     *
     * @return array<string, string>
     */
    protected function panels(): array
    {
        return [];
    }

    public function register(): void
    {
        $aliases = $this->classAliases();
        if ($aliases === []) {
            return;
        }

        spl_autoload_register(static function (string $class) use ($aliases): void {
            if (isset($aliases[$class])) {
                class_alias($aliases[$class], $class);
            }
        });
    }

    public function boot(): void
    {
        $path = $this->modulePath();
        if ($this->roleDefaults() !== []) {
            config(['permissions.roles' => config('permissions.roles', []) + $this->roleDefaults()]);
        }
        $registry = $this->app->make(ModuleRegistry::class);
        $registry->addModule($this->moduleName(), $this->moduleLabel(), $this->moduleDescription());

        if (is_dir($path.'/resources/views')) {
            $this->loadViewsFrom($path.'/resources/views', $this->moduleName());
        }
        if (is_dir($path.'/database/migrations')) {
            $this->loadMigrationsFrom($path.'/database/migrations');
        }
        if (! $registry->isEnabled($this->moduleName())) {
            return;
        }
        if (! $this->app->routesAreCached() && is_file($path.'/routes.php')) {
            $this->loadRoutesFrom($path.'/routes.php');
        }

        foreach ($this->navItems() as $item) {
            $registry->addNavItem($item['group'], $item);
        }
        foreach ($this->settingsLinks() as $link) {
            $registry->addSettingsLink($link['section'], $link);
        }
        foreach ($this->attachables() as $key => $entity) {
            $registry->addAttachable($key, $entity['label'], $entity['model'], $entity['edit'], $entity['view'] ?? null);
        }
        if ($this->permissionRoutes() !== []) {
            config(['permissions.routes' => array_merge(config('permissions.routes', []), $this->permissionRoutes())]);
        }
        foreach ($this->navGroups() as $group) {
            $registry->addNavGroup($group);
        }
        if ($this->permissionCatalog() !== []) {
            config(['permissions.catalog' => array_merge(config('permissions.catalog', []), $this->permissionCatalog())]);
        }
        if ($this->openRoutes() !== []) {
            config(['permissions.open_routes' => array_merge(config('permissions.open_routes', []), $this->openRoutes())]);
        }
        if ($this->approvalActions() !== []) {
            config(['approvals.actions' => array_merge(config('approvals.actions', []), $this->approvalActions())]);
        }
        if ($this->consoleCommands() !== []) {
            $this->commands($this->consoleCommands());
        }
        $this->callAfterResolving(Schedule::class, fn (Schedule $schedule) => $this->schedule($schedule));
        if ($this->searchTypes() !== []) {
            $registry->addSearchTypes($this->searchTypes());
        }
        if ($this->codeKinds() !== []) {
            $registry->addCodeKinds($this->codeKinds());
        }
        foreach ($this->panels() as $slot => $view) {
            $registry->addPanel($slot, $view);
        }
    }
}
