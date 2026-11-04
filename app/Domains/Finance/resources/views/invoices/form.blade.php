@extends('layouts.app')
@section('title', $invoice ? 'Edit draft invoice' : 'New invoice')
@section('content')
@include('partials.search-select')
@include('sales::orders._record-styles')
@php
    $taxJson = $taxRates->mapWithKeys(fn ($t) => [$t->id => (float) $t->rate_percent])->all();
    $startLines = old('lines', $lines);
    // With the Taxation module the taxes come from its rules: each line says whether it is goods or a service and its code.
    $taxPack = app(\App\Domains\Core\ModuleRegistry::class)->isEnabled('tax') ? \App\Support\TaxJurisdiction::pack() : null;
    // A discount box shows when the setting is on, or when a line already has a discount (a dispatch or order line can bring one).
    $discounts = \App\Domains\Sales\Services\PriceLists::discountsOn() || collect($startLines)->contains(fn ($l) => (float) ($l['discount_percent'] ?? 0) > 0);
@endphp
@php
    $customerTerms = $customer?->payment_terms;
    $extras = [['key' => 'tool', 'label' => 'Pattern amortisation', 'text' => 'Pattern amortisation', 'note' => 'Per casting on the ticked notes; enter the rate'], ['key' => 'sur', 'label' => 'Metal surcharge', 'text' => 'Metal surcharge', 'note' => 'Index amount for the period'], ['key' => 'frt', 'label' => 'Freight', 'text' => 'Freight', 'note' => 'Per dispatch note; enter the rate']];
    $multiTax = ! $taxPack && $taxRates->count() > 1;
    // With charge boxes on, their block carries the sub total, tax, total and net, so this page keeps one totals block only.
    $boxes = \App\Domains\Finance\Services\ChargeBoxes::showsOn('invoice');
@endphp
<style>
    .iv-f { display: flex; flex-direction: column; gap: 6px; } .iv-f > span:first-child { font-size: 12px; color: var(--muted); }
    .iv-grid { display: grid; grid-template-columns: 340px minmax(0, 1fr) 300px; }
    .iv-left { border-right: 1px solid var(--border); padding: 20px 22px; display: flex; flex-direction: column; gap: 16px; font-size: 13px; }
    .iv-box { width: 16px; height: 16px; border-radius: 4px; flex: none; display: flex; align-items: center; justify-content: center; font-size: 11px; color: #fff; background: #fff; border: 1px solid #c9cbcf; }
    .iv-on > .iv-box { background: var(--accent); border-color: var(--accent); }
    .iv-dn { display: flex; gap: 10px; align-items: flex-start; padding: 10px 12px; border-radius: 8px; cursor: pointer; border: 1px solid var(--border); background: #fff; text-align: left; width: 100%; }
    .iv-dn.iv-on { background: #f5f8fe; border-color: #cdd9f5; }
    .iv-x { display: flex; gap: 10px; align-items: center; cursor: pointer; background: none; border: 0; padding: 0; text-align: left; font: inherit; color: inherit; }
    .iv-in { display: inline-block; min-width: 56px; padding: 3px 6px; border: 1px solid var(--border); border-radius: 5px; width: 90px; text-align: right; }
    .iv-chk { display: grid; grid-template-columns: 20px minmax(0, 1fr); gap: 10px; align-items: start; }
    .iv-chk > span { width: 20px; height: 20px; border-radius: 999px; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 600; }
    @media (max-width: 1100px) { .iv-grid { grid-template-columns: 1fr; } .iv-left { border-right: 0; border-bottom: 1px solid var(--border); } }
</style>
<form method="POST" action="{{ $invoice ? route('invoices.update', $invoice) : route('invoices.store') }}" x-data="invoiceLines(@js($startLines), @js($taxJson), @js((object) $products), @js(Route::has('price-lists.lookup') ? route('price-lists.lookup') : null), @js($dispatches), @js(['terms' => $customerTerms, 'dueDays' => \App\Domains\Finance\Services\FinanceRules::dueDays(), 'create' => ! $invoice, 'url' => route('invoices.create')]))" x-ref="form">@csrf @if ($invoice) @method('PUT') @endif
<input type="hidden" name="source_type" value="{{ old('source_type', $head['source_type'] ?? 'manual') }}"><input type="hidden" name="source_id" value="{{ old('source_id', $head['source_id'] ?? '') }}">
@if ($notice)<p class="el-note" style="margin:0 0 8px">{{ $notice }}</p>@endif
@if ($errors->any())<div class="flash flash-error">{{ $errors->first() }}</div>@endif
<div class="el-card" style="overflow:hidden">
<div class="el-bar"><div style="display:flex;align-items:center;gap:16px;font-size:13px"><a href="{{ route('invoices.index') }}" style="font-weight:600;color:inherit;text-decoration:none">Invoices</a><span class="el-sub">/</span><span>{{ $invoice ? 'Edit draft' : 'New invoice' }}</span></div>
<div style="display:flex;gap:8px"><a class="el-btn" href="{{ $invoice ? route('invoices.show', $invoice) : route('invoices.index') }}">Cancel</a><button class="el-btn" type="submit">Save draft</button><button class="el-btn primary" type="submit" :disabled="! lines.length" :style="lines.length ? '' : 'opacity:.6'">Create invoice</button></div></div>
<div class="iv-grid">
<div class="iv-left">
    <label class="iv-f"><span>Customer</span><select name="customer_id" required @change="if (cfg.create && $event.target.value) { location = cfg.url + '?customer=' + $event.target.value; }"><option value="">—</option>@foreach ($customers as $c)<option value="{{ $c->id }}" @selected(old('customer_id', $head['customer_id'] ?? null) === $c->id)>{{ $c->name }}</option>@endforeach</select></label>
    <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px">
        <label class="iv-f"><span>Invoice date</span><input type="date" name="invoice_date" x-model="date" required></label>
        <label class="iv-f"><span>Terms</span><input name="payment_terms" x-model="terms" maxlength="100" placeholder="{{ $customerTerms ? $customerTerms.' · from customer' : 'Net '.\App\Domains\Finance\Services\FinanceRules::dueDays() }}"></label>
    </div>
    <label class="iv-f"><span>Customer PO</span><input name="customer_reference" x-model="po" maxlength="100"></label>
    <details style="font-size:12.5px"><summary class="el-sub" style="cursor:pointer">Due date, currency{{ $taxPack ? ', place of supply' : '' }}</summary>
        <div style="display:flex;flex-direction:column;gap:12px;margin-top:10px">
            <label class="iv-f"><span>Due date</span><input type="date" name="due_date" x-model="due" value="{{ old('due_date', $invoice?->due_date?->toDateString()) }}"><span class="el-sub">Blank: from the terms</span></label>
            <label class="iv-f"><span>Currency</span><input name="currency" maxlength="10" value="{{ old('currency', $currency) }}"></label>
            @if ($taxPack)<label class="iv-f"><span>Place of supply</span><select name="place_of_supply"><option value="">Customer's region</option>@foreach ($taxPack->regions() as $r => $name)<option value="{{ $r }}" @selected(old('place_of_supply', $head['place_of_supply'] ?? null) === $r)>{{ $name }}</option>@endforeach</select></label>@endif
        </div></details>
    <div style="border-top:1px solid var(--border)"></div>
    <div style="display:flex;justify-content:space-between;align-items:baseline"><div class="el-h">Not yet invoiced</div><span class="el-sub">{{ count($dispatches) }} dispatch {{ \Illuminate\Support\Str::plural('note', count($dispatches)) }}</span></div>
    @forelse ($dispatches as $d)
        <button type="button" class="iv-dn" :class="on('{{ $d['id'] }}') && 'iv-on'" @click="toggleDn('{{ $d['id'] }}')"><span class="iv-box" x-text="on('{{ $d['id'] }}') ? '✓' : ''"></span><span style="flex:1;min-width:0"><span style="display:flex;justify-content:space-between;gap:8px"><code style="font-weight:600">{{ $d['code'] }}</code><span class="el-sub">{{ $d['date'] }}</span></span><span style="display:block;color:#55585d;margin-top:2px">{{ $d['desc'] }}</span><span class="el-sub" style="display:block;margin-top:2px">{{ $d['so'] }}</span></span></button>
    @empty
        <div class="el-sub">{{ $head['customer_id'] ?? null ? 'Nothing waiting to be billed for this customer.' : 'Pick a customer to see the dispatch notes waiting to be billed.' }}</div>
    @endforelse
    <div style="border-top:1px solid var(--border)"></div>
    <div class="el-h">Add to invoice</div>
    @foreach ($extras as $x)
        <button type="button" class="iv-x" :class="extra('{{ $x['key'] }}') && 'iv-on'" @click="toggleExtra('{{ $x['key'] }}', @js($x['label']), @js($x['text']))"><span class="iv-box" x-text="extra('{{ $x['key'] }}') ? '✓' : ''"></span><span style="flex:1"><span style="display:block">{{ $x['label'] }}</span><span class="el-sub" style="display:block">{{ $x['note'] }}</span></span></button>
    @endforeach
</div>
<div class="el-main">
    <div style="display:flex;justify-content:space-between;align-items:baseline"><div class="el-h">Lines</div><span class="el-sub" x-text="(orders ? 'Prices from ' + orders + ' · ' : '') + 'edit any price or quantity'"></span></div>
    <div class="el-table" style="margin:0 -12px"><table><thead><tr><th>Line</th><th>Description</th><th>Trace</th>@if ($taxPack)<th>Tax</th>@elseif ($multiTax)<th>Tax</th>@endif<th class="el-r">Qty</th><th class="el-r">Unit price</th>@if ($discounts)<th class="el-r">Disc %</th>@endif<th class="el-r">Amount</th><th></th></tr></thead><tbody>
    <template x-for="(l, i) in lines" :key="l.key"><tr style="cursor:default">
        <td><code x-text="products[l.product_id]?.code || l.label || ''"></code></td>
        <td style="min-width:200px"><template x-if="! l.dispatch_item_id && ! l.extra"><select :name="`lines[${i}][product_id]`" x-model="l.product_id" @change="pick(l, $event.target.value)" data-search-select style="width:100%;margin-bottom:4px" aria-label="Product"><option value="">Free text</option>@foreach ($products as $id => $p)<option value="{{ $id }}" data-sub="{{ $p['code'] }}">{{ $p['name'] }}</option>@endforeach</select></template>
            <template x-if="l.dispatch_item_id || l.extra"><input type="hidden" :name="`lines[${i}][product_id]`" :value="l.product_id ?? ''"></template>
            <input :name="`lines[${i}][description]`" x-model="l.description" maxlength="300" style="width:100%" :placeholder="l.product_id ? '' : 'Type what is sold'">
            <input type="hidden" :name="`lines[${i}][sales_order_line_id]`" :value="l.sales_order_line_id ?? ''"><input type="hidden" :name="`lines[${i}][dispatch_item_id]`" :value="l.dispatch_item_id ?? ''"><input type="hidden" :name="`lines[${i}][uom]`" :value="l.uom ?? ''">
            <input type="hidden" :name="`lines[${i}][inventory_class]`" :value="l.inventory_class ?? ''">@unless ($taxPack)<input type="hidden" :name="`lines[${i}][supply_type]`" :value="l.supply_type"><input type="hidden" :name="`lines[${i}][tariff_code]`" :value="l.tariff_code">@endunless @unless ($discounts)<input type="hidden" :name="`lines[${i}][discount_percent]`" :value="l.discount_percent ?? 0">@endunless @unless ($multiTax)<input type="hidden" :name="`lines[${i}][tax_rate_id]`" :value="l.tax_rate_id">@endunless</td>
        <td><span class="el-sub" x-text="trace(l)"></span></td>
        @if ($taxPack)<td><select :name="`lines[${i}][supply_type]`" x-model="l.supply_type"><option value="GOODS">Goods</option><option value="SERVICE">Service</option></select> <input :name="`lines[${i}][tariff_code]`" x-model="l.tariff_code" maxlength="20" style="width:90px" placeholder="{{ $taxPack->tariffLabel() }}"><select :name="`lines[${i}][tax_rate_id]`" x-model="l.tax_rate_id" title="Tax if no rule applies"><option value="">No tax</option>@foreach ($taxRates as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach</select></td>
        @elseif ($multiTax)<td><select :name="`lines[${i}][tax_rate_id]`" x-model="l.tax_rate_id"><option value="">No tax</option>@foreach ($taxRates as $t)<option value="{{ $t->id }}">{{ $t->name }} ({{ \App\Support\Formats::number($t->rate_percent, 2) }}%)</option>@endforeach</select></td>@endif
        <td class="el-r"><input class="iv-in" type="number" step="any" min="0" :name="`lines[${i}][quantity]`" x-model.number="l.quantity"></td>
        <td class="el-r"><input class="iv-in" type="number" step="any" min="0" :name="`lines[${i}][unit_price]`" x-model.number="l.unit_price"></td>
        @if ($discounts)<td class="el-r"><input class="iv-in" style="width:64px" type="number" step="any" min="0" max="100" :name="`lines[${i}][discount_percent]`" x-model.number="l.discount_percent"></td>@endif
        <td class="el-r" x-text="money(net(l))"></td>
        <td><button type="button" class="btn btn-small" @click="removeLine(i)" aria-label="Remove line">✕</button></td>
    </tr></template></tbody></table></div>
    <div x-show="! lines.length" style="padding:28px;text-align:center;border:1px dashed #d9dbde;border-radius:8px;color:var(--muted)">Tick a dispatch note to add its lines.</div>
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:24px"><button type="button" class="el-btn" @click="addLine()">Add line</button>
        @unless ($boxes)<div style="width:320px;display:flex;flex-direction:column;gap:6px;font-size:13px">
            <div style="display:flex;justify-content:space-between"><span class="el-sub" style="font-size:13px">Subtotal</span><span x-text="money(subtotal)"></span></div>
            @if ($taxPack)<div style="display:flex;justify-content:space-between"><span class="el-sub" style="font-size:13px">Sales tax</span><span class="el-sub" style="font-size:13px">worked out when saved</span></div>
            @else<div style="display:flex;justify-content:space-between"><span class="el-sub" style="font-size:13px">Sales tax</span><span x-text="tax ? money(tax) : '0.00 · exempt'"></span></div>
            <div style="display:flex;justify-content:space-between;border-top:1px solid var(--border);padding-top:8px;font-weight:600;font-size:15px"><span>Total due</span><span x-text="money(subtotal + tax)"></span></div>@endif
        </div>@endunless
        </div>
    <label class="iv-f"><span>Note on invoice</span><textarea name="notes" rows="2" maxlength="2000" placeholder="Optional. Printed above the terms.">{{ old('notes', $invoice?->notes) }}</textarea></label>
</div>
<div class="el-side" style="gap:14px">
    <div class="el-h">Checks</div>
    <template x-for="(k, i) in checks" :key="i"><div class="iv-chk"><span :style="k.ok ? 'background:#e7f4ec;color:#1d7048' : 'background:#fbf0dc;color:#94610a'" x-text="k.ok ? '✓' : '!'"></span><div><div style="font-weight:500" x-text="k.t"></div><div style="color:#55585d" x-text="k.d"></div></div></div></template>
    <div style="border-top:1px solid var(--border)"></div>
    <div class="el-h">Will be</div>
    <div style="display:flex;justify-content:space-between;gap:12px"><span class="el-sub" style="font-size:13px">Number</span><span><code>{{ $nextNumber }}</code></span></div>
    <div style="display:flex;justify-content:space-between;gap:12px"><span class="el-sub" style="font-size:13px">Due</span><span x-text="dueText"></span></div>
    <div style="display:flex;justify-content:space-between;gap:12px"><span class="el-sub" style="font-size:13px">Template</span><span>@if (Route::has('invoice-templates.index'))<a href="{{ route('invoice-templates.index') }}">{{ $template }}</a>@else{{ $template }}@endif</span></div>
    <div style="display:flex;justify-content:space-between;gap:12px"><span class="el-sub" style="font-size:13px">Send to</span><span>{{ $customer?->email ?: '—' }}</span></div>
    <div style="color:#55585d;line-height:1.5;text-wrap:pretty">Ticked dispatch notes are locked to this invoice so they can't be billed twice. Unticked ones stay in the list for the next invoice.</div>
</div>
</div>
@include('finance::invoices._charges', ['document' => 'invoice', 'flat' => true, 'adjustments' => old('adjustments', $invoice?->adjustments ?? [])])
</div>
</form>
<script>
function invoiceLines(start, rates, products, lookup, dispatches, cfg) {
    let seq = 0;
    const blank = l => ({ ...l, key: ++seq, product_id: l.product_id ?? '', tax_rate_id: l.tax_rate_id ?? '', supply_type: l.supply_type ?? 'GOODS', tariff_code: l.tariff_code ?? '', inventory_class: l.inventory_class ?? '', dn: l.dn ?? null, extra: l.extra ?? null, label: l.label ?? '' });
    return {
        products, cfg, dispatches,
        lines: start.map(blank),
        date: @js(old('invoice_date', $invoice?->invoice_date?->toDateString() ?? today()->toDateString())),
        terms: @js(old('payment_terms', $invoice?->payment_terms)),
        po: @js(old('customer_reference', $head['customer_reference'] ?? '')),
        due: @js(old('due_date', $invoice?->due_date?->toDateString())),
        net(l) { return Math.round((Number(l.quantity) || 0) * (Number(l.unit_price) || 0) * (1 - Math.min(100, Math.max(0, Number(l.discount_percent) || 0)) / 100) * 100) / 100; },
        lineTax(l) { return Math.round(this.net(l) * (rates[l.tax_rate_id] || 0)) / 100; },
        get subtotal() { return this.lines.reduce((n, l) => n + this.net(l), 0); },
        get tax() { return this.lines.reduce((n, l) => n + this.lineTax(l), 0); },
        get ticked() { return this.dispatches.filter(d => this.on(d.id)); },
        get orders() { return [...new Set(this.ticked.flatMap(d => (d.so || '').split(', ')).filter(Boolean))].join(', '); },
        // Tooling follows the castings ticked and freight the dispatch notes ticked, as the lines are built.
        syncExtras() {
            const pcs = this.ticked.reduce((n, d) => n + (Number(d.pcs) || 0), 0), dns = this.ticked.length;
            this.lines.forEach(l => {
                if (l.extra === 'tool' && pcs) { l.quantity = pcs; }
                if (l.extra === 'frt' && dns) { l.quantity = dns; }
            });
        },
        on(id) { return this.lines.some(l => l.dn === id); },
        extra(k) { return this.lines.some(l => l.extra === k); },
        toggleDn(id) {
            if (this.on(id)) { this.lines = this.lines.filter(l => l.dn !== id); this.syncExtras(); return; }
            const d = this.dispatches.find(x => x.id === id);
            const at = this.lines.findIndex(l => l.extra);
            const add = d.lines.map(blank);
            this.lines.splice(at < 0 ? this.lines.length : at, 0, ...add);
            this.syncExtras();
        },
        toggleExtra(k, label, text) {
            if (this.extra(k)) { this.lines = this.lines.filter(l => l.extra !== k); return; }
            const tax = this.lines.find(l => l.tax_rate_id)?.tax_rate_id ?? '';
            this.lines.push(blank({ extra: k, label, description: text, quantity: 1, uom: '', unit_price: 0, discount_percent: 0, tax_rate_id: tax }));
            this.syncExtras();
        },
        addLine() { this.lines.push(blank({ description: '', quantity: 1, uom: '', unit_price: 0, discount_percent: 0, tax_rate_id: this.lines.find(l => l.tax_rate_id)?.tax_rate_id ?? '' })); },
        removeLine(i) { this.lines.splice(i, 1); },
        trace(l) {
            if (l.extra === 'tool') { return 'Per casting'; }
            if (l.extra === 'sur') { return 'Index amount'; }
            if (l.extra === 'frt') { return this.ticked.map(d => d.code).join(', '); }
            const d = this.dispatches.find(x => x.id === l.dn); return d ? d.code : '';
        },
        get dueText() {
            let d = this.due ? new Date(this.due) : new Date(this.date);
            if (isNaN(d)) { return '—'; }
            if (! this.due) { const m = (this.terms || cfg.terms || '').match(/(\d{1,3})/); d.setDate(d.getDate() + (m ? Number(m[1]) : cfg.dueDays)); }
            return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
        },
        get checks() {
            if (! this.lines.length) { return [{ ok: false, t: 'Nothing to bill', d: 'Tick at least one dispatch note, or add a line.' }]; }
            const n = new Set(this.lines.map(l => l.dn).filter(Boolean)).size;
            const zero = this.lines.filter(l => ! (Number(l.unit_price) > 0)).length;
            return [
                { ok: true, t: 'Quantities', d: n ? 'Taken from ' + n + ' dispatch ' + (n > 1 ? 'notes' : 'note') + '.' : 'Typed in by hand.' },
                { ok: zero === 0, t: 'Prices', d: zero ? zero + (zero > 1 ? ' lines have' : ' line has') + ' no price yet.' : 'Every line is priced.' },
                { ok: !! this.po, t: 'Customer PO', d: this.po ? 'Quoted as ' + this.po + '.' : 'No customer PO on this invoice.' },
            ];
        },
        // A picked product fills the line: its name, unit, tax details and price. The price is the customer's list price when a
        // list prices it, else the last price it was sold at; the line stays editable either way.
        pick(l, id) {
            const p = products[id];
            if (! p) { return; }
            Object.assign(l, { description: (p.code ? p.code + ' ' : '') + p.name, uom: p.uom, supply_type: p.supply_type || 'GOODS', tariff_code: p.tariff_code ?? '', inventory_class: p.inventory_class ?? '' });
            if (p.price !== null) { l.unit_price = p.price; }
            if (! lookup) { return; }
            const form = this.$refs.form;
            const q = new URLSearchParams({ product: 'product:' + id, customer: form.customer_id.value, date: form.invoice_date.value, qty: Number(l.quantity) || 1, uom: l.uom === 'kg' ? 'kg' : 'piece' });
            fetch(lookup + '?' + q, { headers: { Accept: 'application/json' } }).then(r => r.ok ? r.json() : { price: null }).then(j => {
                if (j.price !== null && l.product_id === id) { l.unit_price = Number(j.price); }
            }).catch(() => {});
        },
        money(v) { return (Math.round(v * 100) / 100).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
    };
}
</script>
@endsection
