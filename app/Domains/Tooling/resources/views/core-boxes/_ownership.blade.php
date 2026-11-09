{{-- Who owns the core box and where it is (design 18h), like the pattern ownership panel without paying off or handing back. --}}
@php
    $maker = Route::has('developments.index') ? \App\Domains\Development\Models\PatternDevelopment::touching($box)->first(fn ($d) => $d->tools->contains(fn ($t) => $t->core_box_id === $box->id && $t->route === 'NEW')) : null;
    $makerTool = $maker?->tools->first(fn ($t) => $t->core_box_id === $box->id);
    $paidBy = match (true) {
        $makerTool === null || $makerTool->who_pays === null => null,
        $makerTool->who_pays === 'CUSTOMER' => ($maker->customer?->name ?? 'Customer').', on '.$maker->code,
        default => config('pattern_developments.who_pays.'.$makerTool->who_pays).', on '.$maker->code,
    };
    $editable = ! $box->trashed();
@endphp
<div class="panel" id="ownership">
    <div class="panel-header"><h2>Ownership and custody</h2></div>
    <div class="panel-body">
        <div class="record-list">
            @include('partials.record-row', ['icon' => 'building', 'title' => $box->ownerLabel(),
                'sub' => collect([$paidBy ? 'Paid by '.$paidBy : null, $box->ownership_reference ? 'Ownership tag '.$box->ownership_reference : null, $box->return_notice_days !== null ? 'Return on request: '.($box->return_notice_days ? $box->return_notice_days.' days' : 'at once') : null])->filter()->implode(' · ') ?: 'No paper recorded',
                'editTarget' => $editable ? 'cb-ownership-edit' : null, 'editLabel' => 'Change owner'])
            @include('partials.record-row', ['icon' => 'map-pin', 'title' => $box->custodyLabel(), 'sub' => 'Where the core box is now',
                'editTarget' => $editable ? 'cb-ownership-edit' : null, 'editLabel' => 'Change location'])
        </div>
    </div>
    @if ($editable)
    <div class="panel-body" style="border-top:1px solid var(--border)">
        <details id="cb-ownership-edit" @if ($errors->hasAny(['owner_type', 'owner_customer_id', 'custody', 'custody_location', 'return_notice_days'])) open @endif>
            <summary class="btn">Change owner or location</summary>
            <form method="POST" action="{{ route('core-boxes.ownership', $box) }}" style="margin-top:12px">@csrf @method('PUT')
                <div class="form-grid">
                    <div class="field"><label for="cb-owner_type">Owner *</label><select id="cb-owner_type" name="owner_type">@foreach (['FOUNDRY' => 'Foundry', 'CUSTOMER' => 'Customer'] as $k => $l)<option value="{{ $k }}" @selected(old('owner_type', $box->owner_type) === $k)>{{ $l }}</option>@endforeach</select></div>
                    <div class="field"><label for="cb-owner_customer_id">Customer</label><select data-search-select id="cb-owner_customer_id" name="owner_customer_id"><option value="">—</option>@foreach ($customers as $c)<option value="{{ $c->id }}" @selected(old('owner_customer_id', $box->owner_customer_id) === $c->id)>{{ $c->name }}</option>@endforeach</select>@error('owner_customer_id')<div class="error">{{ $message }}</div>@enderror</div>
                    <div class="field"><label for="cb-ownership_reference">Ownership tag</label><input id="cb-ownership_reference" name="ownership_reference" maxlength="100" value="{{ old('ownership_reference', $box->ownership_reference) }}" placeholder="Tag on the box, e.g. NL-T-0121"></div>
                    <div class="field"><label for="cb-return_notice_days">Return on request, days' notice</label><input id="cb-return_notice_days" type="number" name="return_notice_days" min="0" max="365" value="{{ old('return_notice_days', $box->return_notice_days) }}"><div class="help">Leave empty when the customer cannot ask for it back.</div>@error('return_notice_days')<div class="error">{{ $message }}</div>@enderror</div>
                    <div class="field"><label for="cb-custody">Where it is *</label><select id="cb-custody" name="custody">@foreach (\App\Models\CoreBox::CUSTODIES as $k)<option value="{{ $k }}" @selected(old('custody', $box->custody) === $k)>{{ \App\Models\Pattern::CUSTODIES[$k] }}</option>@endforeach</select></div>
                    <div class="field"><label for="cb-custody_location">Location / with whom</label><input id="cb-custody_location" name="custody_location" maxlength="150" value="{{ old('custody_location', $box->custody_location) }}" placeholder="Rack CS-R2-04, or the supplier's name">@error('custody_location')<div class="error">{{ $message }}</div>@enderror</div>
                </div>
                <div class="actions"><button class="btn btn-primary" type="submit">Save ownership</button></div>
            </form>
        </details>
    </div>
    @endif
</div>
