{{-- Design 10f: the overview tab's edit form, opened in place by Edit product. The side panel lists unsaved changes and what they
     affect; weight, alloy and tooling changes suggest saving as a new draft revision. $product, $facts. --}}
@php
    $f = $facts;
    $revision = $f['revision'];
    $customers = \Illuminate\Support\Facades\DB::table('customers as c')->join('parties as pa', 'pa.id', '=', 'c.party_id')->orderBy('pa.name')->get(['c.id', 'pa.name']);
    $grades = \App\Models\IronGrade::listed();
    $patterns = \App\Models\Pattern::orderBy('code')->get(['id', 'code', 'name']);
    $nextRevision = \App\Models\ProductRevision::nextLabel($product->revisions->pluck('revision'));
    $val = fn (string $field, $current) => old($field, $current);
    $num = fn ($v, int $d = 2) => $v === null || $v === '' ? '' : rtrim(rtrim(number_format((float) $v, $d, '.', ''), '0'), '.');

    // Material cost per casting moves with the poured weight through the lines counted per kg poured.
    $perKgPoured = $f['bom']->filter(fn ($l) => $l['line']->basis === 'PER_KG_POURED' && $l['line']->material?->standardCost() !== null)
        ->sum(fn ($l) => (float) $l['line']->quantity * (float) $l['line']->material->standardCost());
    $effect = [
        'materialCost' => $f['materialCost'],
        'perKgPoured' => $perKgPoured,
        'pouredKg' => $f['pouredKg'],
        'openOrder' => $f['openOrder'] ? ['number' => $f['openOrder']->order_number, 'qty' => (float) $f['openOrder']->ordered_qty] : null,
        'currency' => \App\Support\Formats::withCurrency('#'),
    ];
@endphp
<form method="POST" action="{{ route('products.update', $product) }}" id="pd-edit-form" class="pd-grid pd-edit" data-effect="{{ json_encode($effect) }}">
    @csrf
    @method('PUT')
    <input type="hidden" name="_pd_edit" value="1">
    <div class="pe-main">
        @if ($errors->any())<div class="alert alert-error" role="alert">{{ $errors->first() }}</div>@endif

        <div class="pe-sec">
            <div class="pd-h">Identity</div>
            <div class="pe-grid">
                <label class="pe-f">Product code<input value="{{ $product->code }}" class="mono ro" readonly><span class="pe-help">Set on creation</span></label>
                <label class="pe-f">Name<input name="name" value="{{ $val('name', $product->name) }}" required maxlength="255" data-label="Name"></label>
                <label class="pe-f">Status<select name="status" data-label="Status">@foreach (\App\Models\Product::STATUSES as $code => $label)<option value="{{ $code }}" @selected($val('status', $product->status ?: ($product->active ? 'ACTIVE' : 'OBSOLETE')) === $code)>{{ $label }}</option>@endforeach</select></label>
                <label class="pe-f">Revision<input value="{{ $revision?->revision ?? '—' }}" class="mono ro" readonly><span class="pe-help">{{ $revision ? ($revision->status === 'APPROVED' ? 'Released' : ucfirst(strtolower($revision->status))).($revision->effective_from ? ' '.$revision->effective_from->format('j M') : '') : 'None yet' }}</span></label>
            </div>
        </div>
        <div class="pe-rule"></div>

        <div class="pe-sec">
            <div class="pd-h">Customer</div>
            <div class="pe-grid">
                <label class="pe-f">Customer<select name="customer_id" data-label="Customer"><option value="">{{ $f['customer'] && ! $product->customer_id ? $f['customer'].' (from orders)' : 'Not set' }}</option>@foreach ($customers as $c)<option value="{{ $c->id }}" @selected($val('customer_id', $product->customer_id) === $c->id)>{{ $c->name }}</option>@endforeach</select></label>
                <label class="pe-f">Customer part no.<input name="customer_part_number" class="mono" maxlength="80" value="{{ $val('customer_part_number', $revision?->customer_part_number ?? $f['requirement']?->customer_document_number) }}" data-label="Customer part no."></label>
                <label class="pe-f">CSR<input name="csr" maxlength="120" value="{{ $val('csr', $revision?->csr ?? ($f['requirement']?->requirement_code ? $f['requirement']->requirement_code.($f['requirement']->revision_code ? ' rev '.$f['requirement']->revision_code : '') : null)) }}" data-label="CSR"></label>
                <label class="pe-f">Approved for<select name="approved_for" data-label="Approved for"><option value="">Not set</option>@foreach (\App\Models\ProductRevision::APPROVED_FOR as $code => $label)<option value="{{ $code }}" @selected($val('approved_for', $revision?->approved_for) === $code)>{{ $label }}</option>@endforeach</select></label>
            </div>
        </div>
        <div class="pe-rule"></div>

        <div class="pe-sec">
            <div class="pd-h">Technical</div>
            <div class="pe-grid">
                <label class="pe-f">Alloy<select name="iron_grade_id" data-label="Alloy" data-method><option value="">Not set</option>@foreach ($grades as $g)<option value="{{ $g->id }}" @selected($val('iron_grade_id', $f['grade']?->id) === $g->id)>{{ $g->code }}</option>@endforeach</select></label>
                <label class="pe-f">Casting weight, kg<input name="nominal_weight_kg" type="number" step="any" min="0" value="{{ $val('nominal_weight_kg', $num($f['castingKg'], 4)) }}" data-label="Casting weight" data-unit="kg" data-method data-cast></label>
                <label class="pe-f">Poured weight, kg<input name="poured_weight_kg" type="number" step="any" min="0" value="{{ $val('poured_weight_kg', $num($f['pouredKg'], 4)) }}" data-label="Poured weight" data-unit="kg" data-method data-poured @disabled(! $f['cavity'])><span class="pe-help" data-yield>{{ $f['cavity'] ? ($f['castingKg'] && $f['pouredKg'] ? 'Yield '.round(100 * $f['castingKg'] / $f['pouredKg']).'%' : 'Per casting, on the pattern cavity') : 'Link a pattern first' }}</span></label>
                <label class="pe-f">Process<input value="{{ $f['process'] ?: '—' }}" class="ro" readonly><span class="pe-help">{{ $f['patternRevision'] ? 'From the pattern' : 'Comes from the pattern' }}</span></label>
            </div>
            <div class="pe-grid">
                <label class="pe-f">Pattern<select name="pattern_id" class="mono" data-label="Pattern" data-method><option value="">{{ $f['patternRevision'] ? '' : 'Not linked' }}</option>@foreach ($patterns as $p)<option value="{{ $p->id }}" @selected($val('pattern_id', $f['patternRevision']?->pattern_id) === $p->id)>{{ $p->code }}{{ $p->name && $p->name !== $p->code ? ' · '.$p->name : '' }}</option>@endforeach</select></label>
                <label class="pe-f">Core box<input value="{{ $f['cores']->map(fn ($c) => $c->coreDefinition?->coreBoxes()->value('code') ?? $c->coreDefinition?->mark_code)->filter()->implode(', ') ?: '—' }}" class="mono ro" readonly><span class="pe-help">From the cores</span></label>
                <label class="pe-f">Cavities per mould<input value="{{ $f['perMould'] ?? '—' }}" class="ro" readonly><span class="pe-help">On the pattern revision</span></label>
                <label class="pe-f">PPAP level<select name="ppap_level" data-label="PPAP level"><option value="">Not set</option>@foreach ([3, 2, 1, 4, 5] as $level)<option value="{{ $level }}" @selected((string) $val('ppap_level', $revision?->ppap_level) === (string) $level)>Level {{ $level }}</option>@endforeach</select></label>
            </div>
        </div>
        <div class="pe-rule"></div>

        <div class="pe-sec">
            <div class="pd-h">Commercial</div>
            <div class="pe-grid">
                <label class="pe-f">Price per piece{{ \App\Support\Formats::currencySuffix() ? ', '.trim(\App\Support\Formats::currencySuffix(), ' ()') : '' }}<input name="unit_price" type="number" step="any" min="0" value="{{ $val('unit_price', $num($f['price'], 4)) }}" data-label="Price"></label>
                <label class="pe-f">Annual volume<input name="annual_volume" type="number" step="1" min="0" value="{{ $val('annual_volume', $revision?->annual_volume ?? ($f['volume'] > 0 ? (int) $f['volume'] : '')) }}" data-label="Annual volume"></label>
                <label class="pe-f">Packing spec<input name="packing_spec" class="mono" maxlength="80" value="{{ $val('packing_spec', $revision?->packing_spec) }}" data-label="Packing spec"></label>
                <label class="pe-f">Default delivery<select name="default_delivery" data-label="Default delivery"><option value="">Not set</option>@foreach (\App\Models\ProductRevision::DELIVERY as $code => $label)<option value="{{ $code }}" @selected($val('default_delivery', $revision?->default_delivery) === $code)>{{ $label }}</option>@endforeach</select></label>
            </div>
        </div>

        <label class="pe-f">Change note<textarea name="change_note" maxlength="2000" placeholder="What changed and why. A draft revision keeps it as its change.">{{ old('change_note') }}</textarea></label>

        <details class="pe-more" @if ($errors->hasAny(['product_type', 'base_uom', 'inventory_class', 'supply_type', 'tariff_code', 'tax_uom', 'origin_country'])) open @endif>
            <summary>Type, unit and tax details</summary>
            <div class="pe-grid" style="margin-top:12px">
                <label class="pe-f">Product type<select name="product_type" data-label="Product type">@foreach (['CASTING' => 'Casting', 'CORE' => 'Core', 'OTHER' => 'Other'] as $code => $label)<option value="{{ $code }}" @selected($val('product_type', $product->product_type ?: 'CASTING') === $code)>{{ $label }}</option>@endforeach</select></label>
                <label class="pe-f">Base unit<select name="base_uom" data-label="Base unit">@foreach (array_unique(['piece', 'kg', 'set', $product->base_uom ?: 'piece']) as $uom)<option value="{{ $uom }}" @selected($val('base_uom', $product->base_uom ?: 'piece') === $uom)>{{ $uom }}</option>@endforeach</select><span class="pe-help">kg: new order lines sell it by weight</span></label>
                <label class="pe-f" style="grid-column:span 2">Description<input name="description" value="{{ $val('description', $product->description) }}" data-label="Description"></label>
            </div>
            <div style="margin-top:12px">@include('partials.trade-fields', ['item' => $product])</div>
        </details>
    </div>

    <aside class="pd-side">
        <div class="pd-h">Unsaved changes</div>
        <div class="pe-changes" data-changes><div class="pe-none">Nothing changed yet.</div></div>
        <hr>
        <div class="pd-h">Effect</div>
        <div class="pd-row"><span>Material cost</span><span data-effect-cost>{{ $f['materialCost'] !== null ? \App\Support\Formats::money($f['materialCost']) : '—' }}</span></div>
        @if ($f['openOrder'])<div class="pd-row"><span>Metal for {{ $f['openOrder']->order_number }}</span><span data-effect-metal>{{ $f['pouredKg'] ? \App\Support\Formats::number($f['pouredKg'] * (float) $f['openOrder']->ordered_qty / 1000, 1).' t' : '—' }}</span></div>@endif
        <div class="pd-row"><span>Cost stack</span><span>Updates on save</span></div>
        <div class="pd-callout" data-needs-revision hidden>
            <div style="font-weight:600">Needs a new revision</div>
            <div class="pe-callout-text"><span data-method-names>This change</span> changes the method. Save as rev {{ $nextRevision }} draft to keep rev {{ $revision?->revision ?? '—' }} running until the PPAP is resubmitted.</div>
        </div>
        <p class="pd-note">Name, CSR, price and packing save to the current revision.</p>
    </aside>
</form>
