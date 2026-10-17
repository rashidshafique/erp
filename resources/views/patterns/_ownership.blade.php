{{-- Who owns the pattern and where it is, with paying off, handing back, scrapping and the ownership history. --}}
@php
    $isCustomers = $pattern->isCustomers();
    $payOff = $owner['payOff'];
    $closed = in_array($pattern->custody, ['RETURNED', 'SCRAPPED'], true);
@endphp
<div class="panel" id="ownership">
    <div class="panel-header"><h2>Ownership</h2><a class="small" href="{{ route('pattern-ownership.index') }}">All patterns by owner</a></div>
    <div class="panel-body">
        <div class="record-list">
            @include('partials.record-row', ['icon' => 'building', 'title' => $pattern->owner_type === 'FOUNDRY' ? 'Foundry-owned' : $pattern->ownerLabel(),
                'note' => $pattern->supplied_by_customer ? 'supplied by the customer' : null,
                'sub' => collect([$pattern->ownershipQuotation ? 'Quotation '.$pattern->ownershipQuotation->code : null, $pattern->ownership_reference, $pattern->acquired_on ? 'received '.$pattern->acquired_on->format('d M Y') : null, $pattern->insured_value !== null ? 'insured PKR '.number_format((float) $pattern->insured_value, 0) : null])->filter()->implode(' · ') ?: 'No paper recorded',
                'editTarget' => $closed || $pattern->trashed() ? null : 'ownership-edit', 'editLabel' => 'Change owner'])
            @include('partials.record-row', ['icon' => 'map-pin', 'title' => $owner['custody'], 'sub' => 'Where the pattern is now',
                'editTarget' => $closed || $pattern->trashed() ? null : 'ownership-edit', 'editLabel' => 'Change location'])
        </div>
        @if ($pattern->ownership_notes)<p class="small" style="margin:12px 0 0">{{ $pattern->ownership_notes }}</p>@endif

        @if ($pattern->owner_type === 'PAYING_OFF')
            @php $pct = $payOff['target'] ? min(100, round(100 * $payOff['dispatched'] / $payOff['target'])) : 0; @endphp
            <div style="margin-top:14px;padding:10px 12px;border:1px solid var(--border);border-radius:8px">
                <div class="small"><strong>Paying off:</strong> {{ number_format($payOff['dispatched']) }} of {{ number_format((int) $payOff['target']) }} castings dispatched to {{ $pattern->ownerCustomer?->name }} ({{ $pct }} %).
                    @if ($payOff['ready'])<strong>Fully paid off.</strong>@endif</div>
                <div style="height:6px;background:var(--border);border-radius:3px;margin:6px 0 8px"><div style="height:6px;width:{{ $pct }}%;background:var(--accent);border-radius:3px"></div></div>
                @unless ($closed)
                    <form method="POST" action="{{ route('patterns.pay-off', $pattern) }}" style="display:flex;gap:6px;flex-wrap:wrap" onsubmit="return confirm(@js('Record '.$pattern->code.' as paid off? It becomes '.$pattern->ownerCustomer?->name.'\'s property.'))">@csrf
                        <input name="reference" maxlength="100" placeholder="Payment or invoice reference" style="width:220px"><button class="btn {{ $payOff['ready'] ? 'btn-primary' : '' }}" type="submit">Record as paid off</button></form>
                @endunless
            </div>
        @endif
    </div>

    @unless ($closed || $pattern->trashed())
        <div class="panel-body" style="border-top:1px solid var(--border)">
            <details id="ownership-edit" @if ($errors->hasAny(['owner_type', 'owner_customer_id', 'amortise_qty', 'custody', 'custody_location'])) open @endif>
                <summary class="btn">Change owner or location</summary>
                <form method="POST" action="{{ route('patterns.ownership', $pattern) }}" style="margin-top:12px">@csrf @method('PUT')
                    <div class="form-grid">
                        <div class="field"><label for="owner_type">Owner *</label><select id="owner_type" name="owner_type">@foreach (\App\Models\Pattern::OWNERS as $k => $l)<option value="{{ $k }}" @selected(old('owner_type', $pattern->owner_type) === $k)>{{ $l }}</option>@endforeach</select></div>
                        <div class="field"><label for="owner_customer_id">Customer</label><select id="owner_customer_id" name="owner_customer_id"><option value="">—</option>@foreach ($owner['customers'] as $c)<option value="{{ $c->id }}" @selected(old('owner_customer_id', $pattern->owner_customer_id) === $c->id)>{{ $c->name }}</option>@endforeach</select>@error('owner_customer_id')<div class="error">{{ $message }}</div>@enderror</div>
                        <div class="field"><label for="amortise_qty">Castings that pay it off</label><input id="amortise_qty" type="number" name="amortise_qty" min="1" value="{{ old('amortise_qty', $pattern->amortise_qty) }}"><div class="help">Only when it is being paid off, e.g. the quotation's amortisation quantity.</div>@error('amortise_qty')<div class="error">{{ $message }}</div>@enderror</div>
                        <div class="field"><label for="ownership_quotation_id">Quotation</label><select id="ownership_quotation_id" name="ownership_quotation_id"><option value="">—</option>@foreach ($owner['quotations'] as $q)<option value="{{ $q->id }}" @selected(old('ownership_quotation_id', $pattern->ownership_quotation_id) === $q->id)>{{ $q->code }}</option>@endforeach</select></div>
                        <div class="field"><label for="ownership_reference">Customer PO or reference</label><input id="ownership_reference" name="ownership_reference" maxlength="100" value="{{ old('ownership_reference', $pattern->ownership_reference) }}"></div>
                        <div class="field"><label for="acquired_on">Acquired / received on</label><input id="acquired_on" type="date" name="acquired_on" value="{{ old('acquired_on', $pattern->acquired_on?->format('Y-m-d')) }}"></div>
                        <div class="field"><label for="insured_value">Insured value (PKR)</label><input id="insured_value" type="number" name="insured_value" min="0" step="1" value="{{ old('insured_value', $pattern->insured_value !== null ? (int) $pattern->insured_value : '') }}"></div>
                        <div class="field"><label class="checkbox"><input type="checkbox" name="supplied_by_customer" value="1" @checked(old('supplied_by_customer', $pattern->supplied_by_customer))> Supplied by the customer</label></div>
                        <div class="field"><label for="custody">Where it is *</label><select id="custody" name="custody">@foreach (['IN_STORE', 'PATTERN_SHOP', 'LENT'] as $k)<option value="{{ $k }}" @selected(old('custody', $pattern->custody) === $k)>{{ \App\Models\Pattern::CUSTODIES[$k] }}</option>@endforeach</select></div>
                        <div class="field"><label for="custody_location">Location / with whom</label><input id="custody_location" name="custody_location" maxlength="150" value="{{ old('custody_location', $pattern->custody_location) }}" placeholder="Rack B4, or the supplier's name">@error('custody_location')<div class="error">{{ $message }}</div>@enderror</div>
                        <div class="field"><label for="event_reference">Paper for this change</label><input id="event_reference" name="event_reference" maxlength="100" placeholder="Gate pass, letter, delivery note"></div>
                        <div class="field field-full"><label for="ownership_notes">Ownership notes</label><textarea id="ownership_notes" name="ownership_notes" rows="2" maxlength="2000">{{ old('ownership_notes', $pattern->ownership_notes) }}</textarea></div>
                    </div>
                    <div class="actions"><button class="btn btn-primary" type="submit">Save ownership</button></div>
                </form>
            </details>
        </div>
        <div class="panel-body" style="border-top:1px solid var(--border);display:flex;flex-wrap:wrap;gap:12px 32px;align-items:flex-start">
            @if ($isCustomers)
                <form method="POST" action="{{ route('patterns.return', $pattern) }}" style="display:flex;gap:6px;flex-wrap:wrap;align-items:flex-start" onsubmit="return confirm(@js('Return '.$pattern->code.' to '.$pattern->ownerCustomer?->name.'? It can no longer be moulded and is archived.'))">@csrf
                    <input name="reference" required maxlength="100" placeholder="Delivery note / gate pass" style="width:200px" aria-label="Delivery note the pattern left on"><button class="btn" type="submit">Return to customer</button></form>
            @endif
            <form method="POST" action="{{ route('patterns.scrap', $pattern) }}" style="display:flex;gap:6px;flex-wrap:wrap;align-items:flex-start" onsubmit="return confirm(@js('Scrap '.$pattern->code.'? This cannot be undone.'))">@csrf
                <input name="reference" @if ($isCustomers) required @endif maxlength="100" placeholder="{{ $isCustomers ? "Customer's written consent ref." : 'Reference (optional)' }}" style="width:220px" aria-label="Scrap reference">
                <input name="notes" maxlength="2000" placeholder="Why" style="width:160px" aria-label="Why it is scrapped">
                <button class="btn" type="submit">{{ $isCustomers && $owner['scrapRequest']?->status !== 'APPROVED' ? 'Ask to scrap' : 'Scrap' }}</button></form>
            @if ($isCustomers)
                <p class="muted small" style="margin:0;flex-basis:100%">
                    @if ($owner['scrapRequest']?->status === 'PENDING')Scrapping is waiting for approval (requested by {{ $owner['scrapRequest']->requested_by_name }}). <a href="{{ route('approvals.index') }}">Approvals</a>
                    @elseif ($owner['scrapRequest']?->status === 'APPROVED')Scrapping is approved: press Scrap again to carry it out.
                    @else Scrapping a customer's pattern needs their written consent and an approval.@endif
                </p>
            @endif
        </div>
    @endunless

    <div class="panel-header" style="border-top:1px solid var(--border)"><h2 style="font-size:15px">Ownership history</h2></div>
    @if ($pattern->ownershipEvents->isEmpty())
        <div class="empty-state">No change of owner or location recorded.</div>
    @else
        <div class="table-wrap"><table>
            <thead><tr><th>Date</th><th>What</th><th>From</th><th>To</th><th>Reference</th><th>By</th></tr></thead>
            <tbody>
                @foreach ($pattern->ownershipEvents as $e)
                    <tr><td>{{ $e->occurred_on->format('d M Y') }}</td><td>{{ \App\Models\PatternOwnershipEvent::TYPES[$e->event_type] ?? $e->event_type }}@if ($e->notes)<div class="muted small">{{ $e->notes }}</div>@endif</td><td>{{ $e->from_value ?? '—' }}</td><td>{{ $e->to_value ?? '—' }}</td><td class="mono small">{{ $e->reference ?? '—' }}</td><td class="small">{{ $e->recorded_by ?? '—' }}</td></tr>
                @endforeach
            </tbody>
        </table></div>
    @endif
</div>
