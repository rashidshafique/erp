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

        if (is_dir($path.'/resources/views')) {
            $this->loadViewsFrom($path.'/resources/views', $this->moduleName());
        }
        if (is_dir($path.'/database/migrations')) {
            $this->loadMigrationsFrom($path.'/database/migrations');
        }
        if (! $this->app->routesAreCached() && is_file($path.'/routes.php')) {
            $this->loadRoutesFrom($path.'/routes.php');
        }
    }
}
