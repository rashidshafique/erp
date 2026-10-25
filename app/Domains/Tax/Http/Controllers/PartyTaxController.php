<?php

namespace App\Domains\Tax\Http\Controllers;

use App\Domains\Parties\Models\Party;
use App\Domains\Tax\Models\PartyTaxProfile;
use App\Http\Controllers\Controller;
use App\Support\TaxJurisdiction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Saves a customer's or supplier's tax status, region, withholding and registration numbers, from the panel on the partner page. */
class PartyTaxController extends Controller
{
    public function update(Request $request, Party $party): RedirectResponse
    {
        $pack = TaxJurisdiction::pack();
        $d = $request->validate([
            'tax_status' => ['nullable', Rule::in(array_keys($pack?->partyStatuses() ?? []))],
            'region' => ['nullable', Rule::in(array_keys($pack?->regions() ?? []))],
            'is_withholding_agent' => ['nullable', 'boolean'],
            'registrations' => ['nullable', 'array'],
            'registrations.*' => ['nullable', 'string', 'max:60'],
        ]);
        $fields = array_keys($pack?->registrationFields() ?? []);
        PartyTaxProfile::updateOrCreate(['party_id' => $party->id], [
            'tax_status' => $d['tax_status'] ?? null,
            'region' => $d['region'] ?? null,
            'is_withholding_agent' => $request->boolean('is_withholding_agent'),
            'registrations' => array_map('trim', array_filter(array_intersect_key($d['registrations'] ?? [], array_flip($fields)), 'filled')),
        ]);

        return back()->with('success', "{$party->name}'s tax details saved.");
    }
}
