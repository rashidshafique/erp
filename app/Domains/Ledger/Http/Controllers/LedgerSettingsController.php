<?php

namespace App\Domains\Ledger\Http\Controllers;

use App\Domains\Core\ModuleRegistry;
use App\Domains\Finance\Services\ChargeBoxes;
use App\Domains\Ledger\Models\LedgerAccount;
use App\Domains\Ledger\Services\LedgerSettings;
use App\Domains\Ledger\Services\PostingRules;
use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class LedgerSettingsController extends Controller
{
    public function edit(): View
    {
        $chosen = [];
        foreach (array_keys(LedgerSettings::ROLES) as $role) {
            try {
                $chosen[$role] = LedgerSettings::account($role)->id;
            } catch (InvalidArgumentException) {
                $chosen[$role] = null;
            }
        }
        $authorities = app(ModuleRegistry::class)->isEnabled('tax') && class_exists(\App\Domains\Tax\Models\TaxAuthority::class)
            ? \App\Domains\Tax\Models\TaxAuthority::orderBy('code')->get(['code', 'name']) : collect();

        return view('ledger::settings.edit', [
            'accounts' => LedgerAccount::postable()->orderBy('code')->get(), 'chosen' => $chosen,
            'authorities' => $authorities, 'taxMap' => LedgerSettings::taxMap(),
            'topExpenses' => LedgerAccount::whereNull('parent_id')->where('type', 'EXPENSE')->orderBy('code')->get(), 'costOfSales' => LedgerSettings::costOfSales()?->id,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'roles' => ['array'], 'roles.*' => ['nullable', 'uuid', 'exists:ledger_accounts,id'],
            'tax' => ['array'], 'tax.*' => ['nullable', 'uuid', 'exists:ledger_accounts,id'],
            'cost_of_sales' => ['nullable', 'string', 'max:36'],
        ]);
        if ($request->has('cost_of_sales')) {
            $cost = $d['cost_of_sales'] ?? 'none';
            if ($cost !== 'none' && ! LedgerAccount::whereKey($cost)->whereNull('parent_id')->where('type', 'EXPENSE')->exists()) {
                return back()->withErrors(['cost_of_sales' => 'Cost of sales must be a top-level expense account.'])->withInput();
            }
            AppSetting::setValue(LedgerSettings::COST_OF_SALES, $cost, 'Expense group shown before gross profit');
        }
        foreach (LedgerSettings::ROLES as $role => [$label]) {
            if (array_key_exists($role, $d['roles'] ?? [])) {
                AppSetting::setValue('ledger.account.'.$role, (string) ($d['roles'][$role] ?? ''), 'Ledger account for: '.$label);
            }
        }
        AppSetting::setValue(LedgerSettings::TAX_ACCOUNTS, json_encode(array_filter($d['tax'] ?? [])), 'Ledger account for the tax of each authority');

        return back()->with('success', 'Ledger settings saved.');
    }

    /** Sets the date the books are closed to, or with `close_year` the last day of the year that has just ended. */
    public function closing(Request $request): RedirectResponse
    {
        $d = $request->validate(['closed_to' => ['nullable', 'date'], 'close_year' => ['nullable', 'boolean']]);
        $date = $request->boolean('close_year') ? \App\Support\FinancialYear::previous()['end']->toDateString() : (string) ($d['closed_to'] ?? '');
        AppSetting::setValue(LedgerSettings::CLOSED_TO, $date, 'Books closed to this date');

        return back()->with('success', $date === '' ? 'The books are open again.' : 'Books closed to '.\Illuminate\Support\Carbon::parse($date)->displayDate().'.');
    }

    public function postMissing(PostingRules $rules): RedirectResponse
    {
        $result = $rules->postMissing();
        $back = back()->with('success', "Posted {$result['posted']} documents to the ledger.");

        return $result['skipped'] === [] ? $back : $back->withErrors(['ledger' => 'Not posted: '.implode(' ', array_slice($result['skipped'], 0, 10))]);
    }

    /** How many charge and deduction boxes sales documents show, and on which documents (design 19n). */
    public function salesDocuments(): View
    {
        return view('ledger::settings.sales-documents', [
            'charges' => ChargeBoxes::charges(), 'deductions' => ChargeBoxes::deductions(), 'documents' => ChargeBoxes::documents(), 'accounts' => ChargeBoxes::accounts(),
        ]);
    }

    public function saveSalesDocuments(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'charges' => ['required', 'integer', 'min:0', 'max:'.ChargeBoxes::MAX], 'deductions' => ['required', 'integer', 'min:0', 'max:'.ChargeBoxes::MAX],
            'documents' => ['nullable', 'array'], 'documents.*' => ['in:'.implode(',', array_keys(ChargeBoxes::DOCUMENT_LABELS))],
        ]);
        ChargeBoxes::save((int) $d['charges'], (int) $d['deductions'], $d['documents'] ?? []);

        return back()->with('success', 'Sales document settings saved.');
    }
}
