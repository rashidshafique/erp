<?php

namespace App\Domains\Ledger\Http\Controllers;

use App\Domains\Ledger\Models\LedgerAccount;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function index(): View
    {
        return view('ledger::accounts.index', ['accounts' => LedgerAccount::tree()]);
    }

    /** A new account, at the top of the chart or under another; under another it takes that account's type. */
    public function store(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:ledger_accounts,code'], 'name' => ['required', 'string', 'max:150'],
            'parent_id' => ['nullable', 'uuid', 'exists:ledger_accounts,id'], 'type' => ['required_without:parent_id', 'nullable', Rule::in(array_keys(LedgerAccount::TYPES))],
            'description' => ['nullable', 'string', 'max:300'],
        ]);
        $parent = $d['parent_id'] ? LedgerAccount::find($d['parent_id']) : null;
        if ($parent && $parent->lines()->exists()) {
            return back()->withErrors(['account' => "{$parent->label()} already has postings, so it cannot get sub-accounts. Add the account beside it instead."])->withInput();
        }
        LedgerAccount::create(['code' => $d['code'], 'name' => $d['name'], 'parent_id' => $parent?->id, 'type' => $parent->type ?? $d['type'], 'description' => $d['description'] ?? null]);

        return back()->with('success', "Account {$d['code']} {$d['name']} added.");
    }

    public function update(Request $request, LedgerAccount $account): RedirectResponse
    {
        $d = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('ledger_accounts', 'code')->ignore($account->id)], 'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:300'], 'is_active' => ['nullable', 'boolean'],
        ]);
        $account->update(['code' => $d['code'], 'name' => $d['name'], 'description' => $d['description'] ?? null, 'is_active' => (bool) ($d['is_active'] ?? false)]);

        return back()->with('success', "Account {$account->label()} saved.");
    }

    /** Only an account with no postings and no sub-accounts can go; otherwise switch it off. */
    public function destroy(LedgerAccount $account): RedirectResponse
    {
        if ($account->lines()->exists() || $account->isGroup()) {
            return back()->withErrors(['account' => "{$account->label()} has postings or sub-accounts; switch it off instead."]);
        }
        $account->delete();

        return back()->with('success', "Account {$account->label()} deleted.");
    }
}
