<?php

namespace App\Http\Controllers;

use App\Services\Dashboard\DashboardData;
use App\Support\StartPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ErpDashboardController extends Controller
{
    /** The owner's home (design 4a), or the telemetry wall screen with ?layout=telemetry. */
    public function __invoke(Request $request, DashboardData $data): View|RedirectResponse
    {
        if (config('foundry.require_login') && $request->user() && ! $request->user()->isAdmin()) {
            return redirect()->route(StartPage::routeFor($request->user()));   // each role has a home of its own
        }

        if ($request->query('layout') === 'telemetry') {
            return view('dashboard.telemetry', ['layout' => 'telemetry', 'd' => $data->telemetry()]);
        }

        return view('dashboard.owner', ['layout' => 'owner', 'o' => $data->owner()]);
    }
}
