<?php

namespace App\Domains\Core;

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
    }
}
