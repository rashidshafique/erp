<?php

namespace App\Providers;

use App\Domains\Core\Contracts\ModuleAccess;
use App\Domains\Core\Contracts\PeopleDirectory;
use App\Domains\Core\ModuleRegistry;
use App\Domains\Core\NoPeopleDirectory;
use App\Domains\Core\ShowEveryMenuEntry;
use App\Domains\Core\Contracts\MenuFilter;
use App\Domains\Core\OpenModuleAccess;
use App\Models\Heat;
use App\Models\Material;
use App\Services\Audit\Audit;
use App\Services\Stock\StockItemTypes;
use App\Services\Stock\StockMovementTypes;
use App\Support\Formats;
use App\Support\RuleMenuFilter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\DevCommands;
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
        $this->app->singletonIf(MenuFilter::class, ShowEveryMenuEntry::class);
        $this->app->extend(MenuFilter::class, fn (MenuFilter $filter) => $filter instanceof RuleMenuFilter ? $filter : new RuleMenuFilter($filter));
        $this->app->singleton(ModuleRegistry::class);
        $this->app->singletonIf(PeopleDirectory::class, NoPeopleDirectory::class);
        $this->app->singletonIf(\App\Domains\Core\Contracts\TaxCalculator::class, \App\Domains\Core\FlatRateTax::class);
        $this->app->singletonIf(\App\Domains\Core\Contracts\CreditControl::class, \App\Domains\Core\NoCreditControl::class);
        $this->app->singleton(\App\Domains\Core\Tax\TaxJurisdictions::class);
        $this->app->singleton(StockItemTypes::class, function () {
            $types = new StockItemTypes;
            $types->register('material', Material::class);

            return $types;
        });
        $this->app->singleton(StockMovementTypes::class);
        $this->app->singleton(\App\Services\Stock\StockLocationTypes::class);
        $this->app->scoped(\App\Services\Production\MouldAssembler::class);   // one per request, so its last warning can be read back

        // Telescope is for local development only; it never registers, and so never records, outside it.
        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    /** A tunnel (ngrok) serves the page over https but reaches this copy over http; links and forms must follow the address people see. Local installs only. */
    private function followTunnelScheme(): void
    {
        if ($this->app->environment('local') && str_starts_with(strtolower((string) request()->header('X-Forwarded-Proto')), 'https')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }
    }

    public function boot(): void
    {
        Paginator::defaultView('vendor.pagination.minimal');
        $this->followTunnelScheme();
        \Illuminate\Support\Facades\Blade::if('fieldcan', fn (string $kind): bool => \App\Support\Fields::can($kind));
        DevCommands::artisan('schedule:work', 'scheduler');   // automatic heat charges and the other scheduled jobs, under composer run dev
        Formats::registerCarbonMacros();
        $this->recordOwners();
        View::composer('layouts.app', function ($view): void {
            try {
                $heats = Heat::withLight();
            } catch (\Illuminate\Database\QueryException) {
                $heats = collect();   // before the heats table is migrated
            }
            $view->with('topbarHeats', $heats);
        });

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

    /**
     * Every record made by a signed-in person names them (`created_by_id`), and a role limited to its own records in an area sees
     * only those. The limit is a scope on the model, so lists, searches and the record's own page all follow it.
     */
    private function recordOwners(): void
    {
        $has = [];
        \Illuminate\Support\Facades\Event::listen('eloquent.creating: *', function (string $event, array $data) use (&$has): void {
            $model = $data[0] ?? null;
            if (! $model instanceof \Illuminate\Database\Eloquent\Model || ! \Illuminate\Support\Facades\Auth::id()) {
                return;
            }
            $table = $model->getTable();
            $has[$table] ??= \Illuminate\Support\Facades\Schema::hasColumn($table, 'created_by_id');
            if ($has[$table] && $model->getAttribute('created_by_id') === null) {
                $model->setAttribute('created_by_id', \Illuminate\Support\Facades\Auth::id());
            }
        });
        foreach (config('permissions.own', []) as $area => [$class, $columns]) {
            $class::addGlobalScope('own-records', function (\Illuminate\Database\Eloquent\Builder $query) use ($area, $columns): void {
                $user = \Illuminate\Support\Facades\Auth::user();
                if (\App\Support\AccessMap::ownOnly($user, $area)) {
                    $query->where(fn ($q) => collect($columns)->each(fn ($c) => $q->orWhere($query->getModel()->getTable().'.'.$c, $user->id)));
                }
            });
        }
    }
}
