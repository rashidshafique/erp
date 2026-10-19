<?php

namespace App\Http\Controllers;

use App\Domains\Core\ModuleRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ModuleSettingsController extends Controller
{
    public function edit(ModuleRegistry $registry): View
    {
        return view('settings.modules', ['modules' => $registry->modules(), 'disabled' => $registry->disabledModules()]);
    }

    public function update(Request $request, ModuleRegistry $registry): RedirectResponse
    {
        $on = $request->validate(['modules' => ['nullable', 'array'], 'modules.*' => ['string']])['modules'] ?? [];
        $registry->saveDisabled(array_values(array_diff(array_keys($registry->modules()), $on)));

        return back()->with('success', 'Modules saved. The menu and pages change on the next page load.');
    }
}
