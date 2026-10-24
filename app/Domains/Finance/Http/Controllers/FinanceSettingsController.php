<?php

namespace App\Domains\Finance\Http\Controllers;

use App\Domains\Finance\Models\TaxRate;
use App\Domains\Finance\Services\FinanceRules;
use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinanceSettingsController extends Controller
{
    public function edit(): View
    {
        return view('finance::settings.edit', ['dueDays' => FinanceRules::dueDays(), 'currency' => FinanceRules::currency(), 'rates' => TaxRate::orderBy('name')->get()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $d = $request->validate(['due_days' => ['required', 'integer', 'min:0', 'max:365'], 'currency' => ['nullable', 'string', 'max:10']]);
        AppSetting::setValue(FinanceRules::DUE_DAYS, (string) $d['due_days'], 'Days to payment on a new invoice when the customer has no terms');
        AppSetting::setValue(FinanceRules::CURRENCY, (string) ($d['currency'] ?? ''), 'Currency on a new invoice');

        return back()->with('success', 'Invoicing settings saved.');
    }

    public function storeRate(Request $request): RedirectResponse
    {
        $d = $request->validate(['name' => ['required', 'string', 'max:80'], 'rate_percent' => ['required', 'numeric', 'min:0', 'max:100'], 'is_default' => ['nullable', 'boolean']]);
        $this->save(new TaxRate(), $d, (bool) ($d['is_default'] ?? false));

        return back()->with('success', 'Tax rate added.');
    }

    public function updateRate(Request $request, TaxRate $rate): RedirectResponse
    {
        $d = $request->validate(['is_active' => ['nullable', 'boolean'], 'is_default' => ['nullable', 'boolean']]);
        $this->save($rate, ['is_active' => (bool) ($d['is_active'] ?? false)], (bool) ($d['is_default'] ?? false));

        return back()->with('success', 'Tax rate saved.');
    }

    /** Only one rate is the default, and an inactive one cannot be. */
    private function save(TaxRate $rate, array $data, bool $default): void
    {
        $rate->fill(collect($data)->only(['name', 'rate_percent', 'is_active'])->all())->save();
        if ($default && $rate->is_active !== false) {
            TaxRate::where('id', '!=', $rate->id)->update(['is_default' => false]);
        }
        $rate->update(['is_default' => $default && $rate->is_active !== false]);
    }
}
