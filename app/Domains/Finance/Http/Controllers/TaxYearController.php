<?php

namespace App\Domains\Finance\Http\Controllers;

use App\Domains\Finance\Models\TaxRate;
use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Support\FinancialYear;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Finance > Tax & year end: the financial year and the tax rates, with the Taxation and closing panels other modules add to the page. */
class TaxYearController extends Controller
{
    public function edit(): View
    {
        return view('finance::settings.tax-year', ['startMonth' => FinancialYear::startMonth(), 'rates' => TaxRate::orderBy('name')->get()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $d = $request->validate(['year_start_month' => ['required', 'integer', 'between:1,12']]);
        AppSetting::setValue(FinancialYear::START_MONTH, (string) $d['year_start_month'], 'The month the financial year starts in');

        return back()->with('success', 'Financial year saved.');
    }
}
