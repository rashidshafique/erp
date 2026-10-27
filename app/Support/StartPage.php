<?php

namespace App\Support;

use App\Domains\Core\ModuleRegistry;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Where a person lands after signing in: the first page in config('permissions.home') their permissions allow, otherwise the
 * dashboard. An entry can also need its module switched on, or the quick floor screens in use.
 */
class StartPage
{
    public static function routeFor(?User $user): string
    {
        if ($user === null || $user->isAdmin()) {
            return 'dashboard';
        }
        $modules = app(ModuleRegistry::class);
        foreach (config('permissions.home', []) as $entry) {
            if (! $user->hasPermission($entry['permission']) || ! Route::has($entry['route'])) {
                continue;
            }
            if (isset($entry['module']) && ! $modules->isEnabled($entry['module'])) {
                continue;
            }
            if (($entry['rule'] ?? null) === 'quick_screens' && ! FloorRules::usesQuickScreens()) {
                continue;   // the Today board is the start page only where the floor works on the quick screens
            }

            return $entry['route'];
        }

        return 'dashboard';
    }
}
