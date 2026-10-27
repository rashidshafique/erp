<?php

namespace App\Domains\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CreditController extends Controller
{
    /** A customer's credit limit, hold and release, from the business partner page. */
    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $d = $request->validate([
            'credit_limit' => ['nullable', 'numeric', 'min:0'], 'credit_hold' => ['nullable', 'boolean'],
            'credit_hold_reason' => ['nullable', 'required_if:credit_hold,1', 'string', 'max:300'], 'credit_release_until' => ['nullable', 'date', 'after_or_equal:today'],
        ]);
        $customer->forceFill([
            'credit_limit' => $d['credit_limit'] ?? null, 'credit_hold' => (bool) ($d['credit_hold'] ?? false),
            'credit_hold_reason' => ($d['credit_hold'] ?? false) ? ($d['credit_hold_reason'] ?? null) : null, 'credit_release_until' => $d['credit_release_until'] ?? null,
        ])->save();

        return back()->with('success', 'Credit terms saved.');
    }
}
