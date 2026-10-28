<?php

namespace App\Domains\Finance\Http\Controllers;

use App\Domains\Finance\Models\TaxRate;
use App\Domains\Finance\Services\FinanceRules;
use App\Domains\Finance\Services\InvoiceTemplates;
use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FinanceSettingsController extends Controller
{
    public function edit(): View
    {
        return view('finance::settings.edit', ['dueDays' => FinanceRules::dueDays(), 'currency' => FinanceRules::currency(), 'billMatch' => FinanceRules::billMatch(), 'billTolerance' => FinanceRules::billTolerance(), 'advances' => FinanceRules::advances(), 'billCost' => FinanceRules::billPriceToCost(),
            'templates' => app(InvoiceTemplates::class)->options(), 'template' => app(InvoiceTemplates::class)->default(), 'creditCheck' => FinanceRules::creditCheck(), 'creditOrders' => FinanceRules::creditCountsOrders(), 'creditOverdueDays' => FinanceRules::creditOverdueDays()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $d = $request->validate(['due_days' => ['required', 'integer', 'min:0', 'max:365'], 'currency' => ['nullable', 'string', 'max:10'],
            'bill_match' => ['sometimes', Rule::in(array_keys(FinanceRules::MATCH_OPTIONS))], 'bill_tolerance' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'advances' => ['sometimes', Rule::in(array_keys(FinanceRules::ADVANCE_OPTIONS))], 'bill_cost' => ['sometimes', Rule::in(array_keys(FinanceRules::BILL_COST_OPTIONS))],
            'invoice_template' => ['sometimes', Rule::in(array_keys(app(InvoiceTemplates::class)->options()))], 'credit_check' => ['sometimes', Rule::in(array_keys(FinanceRules::CREDIT_OPTIONS))], 'credit_orders' => ['sometimes', 'boolean'], 'credit_overdue_days' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:365']]);
        AppSetting::setValue(FinanceRules::DUE_DAYS, (string) $d['due_days'], 'Days to payment on a new invoice when the customer has no terms');
        AppSetting::setValue(FinanceRules::CURRENCY, (string) ($d['currency'] ?? ''), 'Currency on a new invoice');

        AppSetting::setValue(InvoiceTemplates::DEFAULT, $d['invoice_template'] ?? app(InvoiceTemplates::class)->default(), 'The print layout invoices use unless one is chosen');
        AppSetting::setValue(FinanceRules::BILL_MATCH, $d['bill_match'] ?? FinanceRules::billMatch(), 'What happens when a supplier bill does not match its purchase order');
        AppSetting::setValue(FinanceRules::BILL_TOLERANCE, (string) ($d['bill_tolerance'] ?? FinanceRules::billTolerance()), 'Percent a supplier bill line may go over the order price or received quantity');

        AppSetting::setValue(FinanceRules::CREDIT_CHECK, $d['credit_check'] ?? FinanceRules::creditCheck(), 'What happens when a customer is over its credit limit');
        AppSetting::setValue(FinanceRules::CREDIT_ORDERS, array_key_exists('credit_orders', $d) ? ($d['credit_orders'] ? '1' : '0') : (FinanceRules::creditCountsOrders() ? '1' : '0'), 'Open orders count towards the credit limit');
        AppSetting::setValue(FinanceRules::CREDIT_OVERDUE_DAYS, (string) (array_key_exists('credit_overdue_days', $d) ? (int) $d['credit_overdue_days'] : FinanceRules::creditOverdueDays()), 'Invoices overdue by more than this many days hold a customer back');
        AppSetting::setValue(FinanceRules::BILL_COST, $d['bill_cost'] ?? FinanceRules::billPriceToCost(), 'Whether a supplier bill price becomes the cost of the lots received');
        AppSetting::setValue(FinanceRules::ADVANCES, $d['advances'] ?? FinanceRules::advances(), 'What happens to money received or paid before the invoice or bill');

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
