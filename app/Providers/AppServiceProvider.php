<?php

namespace App\Providers;

use App\Domains\Core\Contracts\ModuleAccess;
use App\Domains\Core\Contracts\PeopleDirectory;
use App\Domains\Core\ModuleRegistry;
use App\Domains\Core\NoPeopleDirectory;
use App\Domains\Core\OpenModuleAccess;
use App\Models\Heat;
use App\Services\Audit\Audit;
use App\Support\Formats;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Audit::class);
        $this->app->singletonIf(ModuleAccess::class, OpenModuleAccess::class);
        $this->app->singleton(ModuleRegistry::class);
        $this->app->singletonIf(PeopleDirectory::class, NoPeopleDirectory::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::defaultView('vendor.pagination.minimal');
        Formats::registerCarbonMacros();
        View::composer('layouts.app', fn ($view) => $view->with('topbarHeats', \Schema::hasTable('heats') ? Heat::withLight() : collect()));

        // Every model change, anywhere in the app, is written to the audit log.
        foreach (['created' => 'CREATED', 'updated' => 'UPDATED', 'deleted' => 'DELETED'] as $event => $action) {
            Event::listen("eloquent.{$event}: *", function (string $name, array $payload) use ($action): void {
                $model = $payload[0] ?? null;
                if ($model instanceof Model) {
                    app(Audit::class)->onModelEvent($action, $model);
                }
            });
        }
    }
}
