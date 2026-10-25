{{-- The Taxation module's panel on a business partner page: tax status, region, withholding and registration numbers. $party. --}}
@php
    $pack = \App\Support\TaxJurisdiction::pack();
    $profile = \App\Domains\Tax\Models\PartyTaxProfile::firstWhere('party_id', $party->id);
    $canEdit = \App\Http\Middleware\EnforceAccess::allows(auth()->user(), 'tax.parties.update');
    $statuses = $pack?->partyStatuses() ?? [];
    $regions = $pack?->regions() ?? [];
@endphp
@if ($pack)
<div class="panel"><div class="panel-header"><h2>Tax</h2>@if ($canEdit)<span class="muted small">Used to pick the taxes on this partner's invoices, bills and orders.</span>@endif</div>
<div class="panel-body">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:16px">
        <div><div class="muted small">Tax status</div><strong>{{ $statuses[$profile?->tax_status] ?? 'Not set' }}</strong></div>
        <div><div class="muted small">Region</div><strong>{{ $regions[$profile?->region] ?? '—' }}</strong></div>
        <div><div class="muted small">Withholding agent</div><strong>{{ $profile?->is_withholding_agent ? 'Yes' : 'No' }}</strong></div>
        @foreach ($pack->registrationFields() as $key => $label)
            @if ($value = $profile?->registrations[$key] ?? null)<div><div class="muted small">{{ $label }}</div><strong class="mono">{{ $value }}</strong></div>@endif
        @endforeach
    </div>
    @if ($canEdit)
        <details style="margin-top:14px"><summary class="btn">Edit tax details</summary>
        <form method="POST" action="{{ route('tax.parties.update', $party) }}" style="margin-top:12px">@csrf @method('PUT')
            <div class="form-grid">
                <div class="field"><label for="tax_status">Tax status</label><select id="tax_status" name="tax_status"><option value="">Not set</option>@foreach ($statuses as $s => $label)<option value="{{ $s }}" @selected($profile?->tax_status === $s)>{{ $label }}</option>@endforeach</select></div>
                <div class="field"><label for="tax_region">Region</label><select id="tax_region" name="region"><option value="">—</option>@foreach ($regions as $r => $name)<option value="{{ $r }}" @selected($profile?->region === $r)>{{ $name }}</option>@endforeach</select><div class="help">Where services to them are supplied, unless a document says otherwise.</div></div>
                <div class="field"><label><input type="hidden" name="is_withholding_agent" value="0"><input type="checkbox" name="is_withholding_agent" value="1" @checked($profile?->is_withholding_agent)> Withholding agent</label><div class="help">They keep back part of the tax and pay it to the authority.</div></div>
                @foreach ($pack->registrationFields() as $key => $label)
                    <div class="field"><label for="tax-reg-{{ $key }}">{{ $label }}</label><input id="tax-reg-{{ $key }}" name="registrations[{{ $key }}]" maxlength="60" value="{{ $profile?->registrations[$key] ?? '' }}"></div>
                @endforeach
            </div>
            <button class="btn btn-primary">Save tax details</button>
        </form></details>
    @endif
</div></div>
@endif
