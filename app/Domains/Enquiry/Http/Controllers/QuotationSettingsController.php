<?php

namespace App\Domains\Enquiry\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Domains\Enquiry\Services\Rates;
use Illuminate\Http\Request;

class QuotationSettingsController extends Controller
{
    public const LABELS = [
        'melt_grey_per_kg' => ['Molten grey iron, per kg poured', 'Charge, energy, alloys and losses, net of returns recovered'],
        'melt_ductile_per_kg' => ['Molten ductile iron, per kg poured', 'As grey plus magnesium treatment and inoculation'],
        'moulding_per_kg' => ['Moulding, per kg poured', null],
        'core_each' => ['Core, each', 'Made and set'],
        'fettling_per_kg' => ['Fettling, per kg of casting', null],
        'heat_treatment_per_kg' => ['Heat treatment, per kg of casting', 'Only when the part is heat treated'],
        'testing_each' => ['Testing share, per casting', null],
        'ndt_each' => ['NDT, per casting', 'Only when NDT is required'],
        'packing_each' => ['Packing, per casting', null],
        'overhead_pct' => ['Overhead, % of conversion cost', 'On everything except metal, machining and other'],
        'margin_pct' => ['Margin, % of price', 'The starting margin on a new quotation'],
        'tooling_markup_pct' => ['Tooling mark-up, %', null],
        'min_margin_pct' => ['Lowest margin without approval, %', 'Below this the quotation asks for approval, if that approval policy is switched on'],
        'default_yield_pct' => ['Yield assumed without an analysis, %', null],
        'scrap_default_pct' => ['Scrap assumed without an analysis, %', null],
    ];

    public function edit()
    {
        return view('enquiry::rates', ['rates' => Rates::get(), 'defaults' => config('quotation.rates'), 'labels' => self::LABELS, 'currency' => config('quotation.currency')]);
    }

    public function update(Request $request)
    {
        $rules = [];
        foreach (Rates::keys() as $k) {
            $rules[$k] = ['required', 'numeric', 'min:0', 'max:1000000'];
        }
        $d = $request->validate($rules);
        foreach ($d as $k => $v) {
            AppSetting::setValue('quotation.'.$k, (string) $v, 'Quotation cost rate: '.$k);
        }

        return back()->with('success', 'Rates saved. They apply to quotations created or recalculated from now on; issued quotations keep their prices.');
    }
}
