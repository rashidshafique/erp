<?php

namespace App\Domains\Licensing\Http\Controllers;

use App\Domains\Core\ModuleRegistry;
use App\Domains\Licensing\SettingsModuleAccess;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ModuleSettingsController extends Controller
{
    public function edit(ModuleRegistry $registry, SettingsModuleAccess $access): View
    {
        $modules = collect($registry->modules())->map(fn (array $module, string $name) => $module + [
            'licensed' => $access->isLicensed($name), 'enabled' => $access->isEnabled($name),
        ])->all();

        return view('licensing::modules', ['modules' => $modules]);
    }

    public function update(Request $request, ModuleRegistry $registry, SettingsModuleAccess $access): RedirectResponse
    {
        $on = $request->validate(['modules' => ['nullable', 'array'], 'modules.*' => ['string']])['modules'] ?? [];
        $names = array_keys($registry->modules());
        $licensed = array_filter($names, $access->isLicensed(...));
        $unlicensedStaysAsItWas = array_intersect($access->disabledModules(), array_diff($names, $licensed));
        $access->saveDisabled(array_values(array_merge(array_diff($licensed, $on), $unlicensedStaysAsItWas)));

        return back()->with('success', 'Modules saved. The menu and pages change on the next page load.');
    }
}
