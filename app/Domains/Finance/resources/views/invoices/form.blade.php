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
    .iv-r { display: grid; grid-template-columns: 100px minmax(0, 1fr); gap: 10px; align-items: center; } .iv-r > span:first-child { font-size: 12px; color: var(--muted); }
    .iv-head { display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr) minmax(0, 1fr); gap: 24px; padding-bottom: 20px; border-bottom: 1px solid var(--border); align-items: start; }
    .iv-addr { padding: 7px 10px; border: 1px solid var(--border); border-radius: 6px; background: #fff; min-height: 76px; line-height: 1.5; white-space: pre-line; }
    .iv-foot { display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr) minmax(0, 1fr); gap: 20px; align-items: start; padding-top: 20px; border-top: 1px solid var(--border); }
    .iv-box { width: 16px; height: 16px; border-radius: 4px; flex: none; display: flex; align-items: center; justify-content: center; font-size: 11px; color: #fff; background: #fff; border: 1px solid #c9cbcf; }
    .iv-on > .iv-box { background: var(--accent); border-color: var(--accent); }
    .iv-dns { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
    .iv-dn { display: flex; gap: 10px; align-items: flex-start; padding: 10px 12px; border-radius: 8px; cursor: pointer; border: 1px solid var(--border); background: #fff; text-align: left; width: 100%; font: inherit; }
    .iv-dn.iv-on { background: #f5f8fe; border-color: #cdd9f5; }
    .iv-x { display: flex; gap: 8px; align-items: center; cursor: pointer; background: none; border: 0; padding: 0; text-align: left; font: inherit; color: inherit; }
    .iv-t { border: 1px solid var(--border); border-radius: 8px; overflow-x: auto; } .iv-t table { table-layout: fixed; width: 100%; margin: 0; }
    .iv-t th { padding: 9px 12px; border-right: 1px solid #eff0f2; background: #fafafb; text-align: left; } .iv-t th:last-child { border-right: 0; }
    .iv-t th.el-r, .iv-t td.el-r { text-align: right; }
    .iv-t td { padding: 0; border-right: 1px solid #eff0f2; vertical-align: middle; height: 44px; } .iv-t td:last-child { border-right: 0; }
    .iv-t td.iv-ro { padding: 0 12px; background: #fafafb; color: var(--muted); }
    .iv-t input.iv-cell, .iv-t select.iv-cell { display: block; width: 100%; height: 44px; box-sizing: border-box; padding: 0 12px; border: 0; border-radius: 0; background: transparent; box-shadow: none; outline: none; font-size: 13.5px; }
    .iv-t input.iv-cell.el-r { text-align: right; }
    .iv-t input.iv-cell:hover { background: #f5f8fe; } .iv-t input.iv-cell:focus, .iv-t select.iv-cell:focus { background: #fff; box-shadow: inset 0 0 0 2px var(--accent); }
    .iv-chk { display: grid; grid-template-columns: 20px minmax(0, 1fr); gap: 10px; align-items: start; }
    .iv-chk > span { width: 20px; height: 20px; border-radius: 999px; display: flex; align-items: center; justify-content: center; font-size: 11.5px; font-weight: 600; }
    .iv-sw { width: 30px; height: 18px; border-radius: 999px; position: relative; background: #d9dbde; flex: none; } .iv-sw i { position: absolute; top: 2px; left: 2px; width: 14px; height: 14px; border-radius: 50%; background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.2); transition: left .15s; }
    .iv-sw.iv-on { background: var(--accent); } .iv-sw.iv-on i { left: 14px; }
    @media (max-width: 1100px) { .iv-head, .iv-foot, .iv-dns { grid-template-columns: 1fr; } }
</style>
<form method="POST" action="{{ $invoice ? route('invoices.update', $invoice) : route('invoices.store') }}" x-data="invoiceLines(@js($startLines), @js($taxJson), @js((object) $products), @js(Route::has('price-lists.lookup') ? route('price-lists.lookup') : null), @js($dispatches), @js(['terms' => $customerTerms, 'dueDays' => \App\Domains\Finance\Services\FinanceRules::dueDays(), 'create' => ! $invoice, 'url' => route('invoices.create')]))" x-ref="form">@csrf @if ($invoice) @method('PUT') @endif
<input type="hidden" name="source_type" value="{{ old('source_type', $head['source_type'] ?? 'manual') }}"><input type="hidden" name="source_id" value="{{ old('source_id', $head['source_id'] ?? '') }}">
@if ($notice)<p class="el-note" style="margin:0 0 8px">{{ $notice }}</p>@endif
@if ($errors->any())<div class="flash flash-error">{{ $errors->first() }}</div>@endif
<div class="el-card" style="overflow:hidden">
<div class="el-bar"><div style="display:flex;align-items:center;gap:16px;font-size:13px"><a href="{{ route('invoices.index') }}" style="font-weight:600;color:inherit;text-decoration:none">Invoices</a><span class="el-sub">/</span><span>{{ $invoice ? 'Edit draft' : 'New invoice' }}</span></div>
<div style="display:flex;gap:8px"><a class="el-btn" href="{{ $invoice ? route('invoices.show', $invoice) : route('invoices.index') }}">Cancel</a><button class="el-btn" type="submit">Save draft</button><button class="el-btn primary" type="submit" :disabled="! lines.length" :style="lines.length ? '' : 'opacity:.6'">Create invoice</button></div></div>
<div style="padding:20px 28px;display:flex;flex-direction:column;gap:20px;font-size:13px">
<div class="iv-head">
    <div style="display:flex;flex-direction:column;gap:6px">
        <label class="iv-f"><span>Customer</span><select name="customer_id" required @change="if (cfg.create && $event.target.value) { location = cfg.url + '?customer=' + $event.target.value; }"><option value="">Select customer</option>@foreach ($customers as $c)<option value="{{ $c->id }}" @selected(old('customer_id', $head['customer_id'] ?? null) === $c->id)>{{ $c->name }}</option>@endforeach</select></label>
        <div class="iv-addr">@if ($customer){{ trim((string) $customer->billing_address) }}@if ($customer->tax_number){{ "\n" }}<span class="el-sub" style="font-size:13px">{{ $customer->tax_number }}</span>@endif @else<span class="el-sub" style="font-size:13px">Address fills in from the customer</span>@endif</div>
    </div>
    <div style="display:flex;flex-direction:column;gap:8px">
        <label class="iv-r"><span>Invoice date</span><input type="date" name="invoice_date" x-model="date" required></label>
        <label class="iv-r"><span>Terms</span><input name="payment_terms" x-model="terms" maxlength="100" placeholder="{{ $customerTerms ? $customerTerms.' · from customer' : 'Net '.\App\Domains\Finance\Services\FinanceRules::dueDays() }}"></label>
        <label class="iv-r"><span>Due date</span><input type="date" name="due_date" x-model="due" value="{{ old('due_date', $invoice?->due_date?->toDateString()) }}"></label>
    </div>
    <div style="display:flex;flex-direction:column;gap:8px">
        <label class="iv-r"><span>Customer PO</span><input name="customer_reference" x-model="po" maxlength="100"></label>
        <div class="iv-r"><span>Send to</span><span>{{ $customer?->email ?: '—' }}</span></div>
        <label class="iv-r"><span>Currency</span><input name="currency" maxlength="10" value="{{ old('currency', $currency) }}"></label>
        @if ($taxPack)<label class="iv-r"><span>Place of supply</span><select name="place_of_supply"><option value="">Customer's region</option>@foreach ($taxPack->regions() as $r => $name)<option value="{{ $r }}" @selected(old('place_of_supply', $head['place_of_supply'] ?? null) === $r)>{{ $name }}</option>@endforeach</select></label>@endif
    </div>
</div>

<div style="display:flex;flex-direction:column;gap:10px">
    <div style="display:flex;justify-content:space-between;align-items:baseline;cursor:pointer" @click="dnOpen = ! dnOpen"><div style="display:flex;gap:8px;align-items:baseline"><span class="el-sub" style="width:10px" x-text="dnOpen ? '▾' : '▸'"></span><div class="el-h">Not yet invoiced</div></div>
        <span class="el-sub">@if (! ($head['customer_id'] ?? null))Select a customer to see their dispatch notes @elseif (! count($dispatches))No dispatch notes waiting for {{ $customer?->name }}@else{{ count($dispatches) }} dispatch {{ \Illuminate\Support\Str::plural('note', count($dispatches)) }} · <span x-text="ticked.length ? ticked.length + ' ticked' : 'tick to add their lines'"></span>@endif</span></div>
    @if (count($dispatches))<div class="iv-dns" x-show="dnOpen">
        @foreach ($dispatches as $d)
        <button type="button" class="iv-dn" :class="on('{{ $d['id'] }}') && 'iv-on'" @click="toggleDn('{{ $d['id'] }}')"><span class="iv-box" style="margin-top:1px" x-text="on('{{ $d['id'] }}') ? '✓' : ''"></span><span style="flex:1;min-width:0"><span style="display:flex;justify-content:space-between;gap:8px"><code style="font-weight:600">{{ $d['code'] }}</code><span class="el-sub">{{ $d['date'] }}</span></span><span style="display:block;color:#55585d;margin-top:2px">{{ $d['desc'] }}</span><span class="el-sub" style="display:block;margin-top:2px">{{ $d['so'] }}</span></span></button>
        @endforeach
    </div>@endif
    <div style="display:flex;gap:20px;align-items:center;flex-wrap:wrap"><span class="el-h">Add to invoice</span>
        @foreach ($extras as $x)
        <button type="button" class="iv-x" :class="extra('{{ $x['key'] }}') && 'iv-on'" @click="toggleExtra('{{ $x['key'] }}', @js($x['label']), @js($x['text']))"><span class="iv-box" x-text="extra('{{ $x['key'] }}') ? '✓' : ''"></span><span>{{ $x['label'] }} <span class="el-sub">{{ $x['note'] }}</span></span></button>
        @endforeach
    </div>
</div>

<div style="display:flex;flex-direction:column;gap:10px">
    <div style="display:flex;justify-content:space-between;align-items:baseline"><div class="el-h">Lines</div><span class="el-sub" x-text="(orders ? 'Prices from ' + orders + ' · ' : '') + 'click any cell to type'"></span></div>
    <div class="iv-t"><table><thead><tr><th style="width:{{ $taxPack ? '16%' : '17%' }}">Product</th><th style="width:{{ $taxPack ? '16%' : '19%' }}">Description</th><th x-show="trace" style="width:11%">Trace</th><th style="width:{{ $taxPack ? '16%' : '10%' }}">{{ $taxPack ? 'Tax' : 'GST' }}</th><th class="el-r" style="width:8%">Qty</th><th style="width:6%">UoM</th><th class="el-r" style="width:9%" title="Price for one unit of the UoM">Unit price</th><th class="el-r" style="width:6%">Disc %</th><th class="el-r" style="width:8%" title="A fixed amount off; worked out from Disc % when that is not 0">Disc amt</th><th class="el-r" style="width:10%">Amount</th></tr></thead><tbody>
    <template x-for="(l, i) in lines" :key="l.key"><tr>
        <td style="height:auto">
            <template x-if="! l.dispatch_item_id && ! l.extra"><select :name="`lines[${i}][product_id]`" x-model="l.product_id" @change="pick(l, $event.target.value)" data-search-select class="iv-cell" aria-label="Product"><option value="">Free text</option>@foreach ($products as $id => $p)<option value="{{ $id }}">{{ $p['code'] }} · {{ $p['name'] }}</option>@endforeach</select></template>
            <template x-if="l.dispatch_item_id || l.extra"><div style="padding:0 12px"><input type="hidden" :name="`lines[${i}][product_id]`" :value="l.product_id ?? ''"><code style="font-weight:600" x-text="products[l.product_id]?.code || l.label || ''"></code></div></template>
            <input type="hidden" :name="`lines[${i}][sales_order_line_id]`" :value="l.sales_order_line_id ?? ''"><input type="hidden" :name="`lines[${i}][dispatch_item_id]`" :value="l.dispatch_item_id ?? ''">
            <input type="hidden" :name="`lines[${i}][inventory_class]`" :value="l.inventory_class ?? ''">@unless ($taxPack)<input type="hidden" :name="`lines[${i}][supply_type]`" :value="l.supply_type"><input type="hidden" :name="`lines[${i}][tariff_code]`" :value="l.tariff_code">@endunless
        </td>
        <td><input class="iv-cell" :name="`lines[${i}][description]`" x-model="l.description" maxlength="300" :placeholder="l.product_id ? '' : 'Type what is sold'" aria-label="Description"></td>
        <td class="iv-ro" x-show="trace" style="font-size:12.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" x-text="trace_(l)"></td>
        @if ($taxPack)<td style="height:auto"><select class="iv-cell" :name="`lines[${i}][supply_type]`" x-model="l.supply_type"><option value="GOODS">Goods</option><option value="SERVICE">Service</option></select><input class="iv-cell" :name="`lines[${i}][tariff_code]`" x-model="l.tariff_code" maxlength="20" placeholder="{{ $taxPack->tariffLabel() }}"><select class="iv-cell" :name="`lines[${i}][tax_rate_id]`" x-model="l.tax_rate_id" title="Tax if no rule applies"><option value="">No tax</option>@foreach ($taxRates as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach</select></td>
        @else<td><select class="iv-cell" :name="`lines[${i}][tax_rate_id]`" x-model="l.tax_rate_id"><option value="">No tax</option>@foreach ($taxRates as $t)<option value="{{ $t->id }}">{{ $t->name }} ({{ \App\Support\Formats::number($t->rate_percent, 2) }}%)</option>@endforeach</select></td>@endif
        <td><input class="iv-cell el-r" type="number" step="any" min="0" :name="`lines[${i}][quantity]`" x-model.number="l.quantity" @focus="$event.target.select()" @keydown.enter.prevent="next($event)" data-col="q"></td>
        <td><select class="iv-cell" :name="`lines[${i}][uom]`" x-model="l.uom" @change="reprice(l)" aria-label="Unit of measure"><template x-for="u in uoms(l)" :key="u"><option :value="u" :selected="u === l.uom" x-text="u"></option></template></select></td>
        <td><input class="iv-cell el-r" type="number" step="any" min="0" :name="`lines[${i}][unit_price]`" x-model.number="l.unit_price" @focus="$event.target.select()" @keydown.enter.prevent="next($event)" data-col="p"></td>
        <td><input class="iv-cell el-r" type="number" step="any" min="0" max="100" :name="`lines[${i}][discount_percent]`" x-model.number="l.discount_percent" @focus="$event.target.select()" @keydown.enter.prevent="next($event)" data-col="d"></td>
        <template x-if="! (Number(l.discount_percent) > 0)"><td><input class="iv-cell el-r" type="number" step="any" min="0" :name="`lines[${i}][discount_amount]`" x-model.number="l.discount_amount" @focus="$event.target.select()" @keydown.enter.prevent="next($event)" data-col="a"></td></template>
        <template x-if="Number(l.discount_percent) > 0"><td class="iv-ro el-r" title="Worked out from Disc %"><input type="hidden" :name="`lines[${i}][discount_amount]`" value="0"><span x-text="money(discAmt(l))"></span></td></template>
        <td class="iv-ro el-r" style="color:inherit;font-weight:500"><button type="button" @click="removeLine(i)" aria-label="Remove line" style="border:0;background:none;color:var(--muted);cursor:pointer;float:left">✕</button><span x-text="money(net(l))"></span></td>
    </tr></template></tbody></table>
    <div x-show="! lines.length" style="padding:28px;text-align:center;color:var(--muted)">Tick a dispatch note to add its lines.</div></div>
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:24px"><div style="display:flex;gap:20px;align-items:center"><button type="button" class="el-btn" @click="addLine()">Add line</button><button type="button" class="iv-x" @click="trace = ! trace"><span class="iv-sw" :class="trace && 'iv-on'"><i></i></span>Show lot trace</button></div>
        @unless ($boxes)<div style="width:320px;display:flex;flex-direction:column;gap:6px;font-size:13px">
            <div style="display:flex;justify-content:space-between"><span class="el-sub" style="font-size:13px">Discount</span><span x-text="discount ? '− ' + money(discount) : '0.00'"></span></div>
            <div style="display:flex;justify-content:space-between"><span class="el-sub" style="font-size:13px">Subtotal</span><span x-text="money(subtotal)"></span></div>
            @if ($taxPack)<div style="display:flex;justify-content:space-between"><span class="el-sub" style="font-size:13px">Sales tax</span><span class="el-sub" style="font-size:13px">worked out when saved</span></div>
            @else<div style="display:flex;justify-content:space-between"><span class="el-sub" style="font-size:13px">Sales tax</span><span x-text="tax ? money(tax) : '0.00 · exempt'"></span></div>
            <div style="display:flex;justify-content:space-between;border-top:1px solid var(--border);padding-top:8px;font-weight:600;font-size:15px"><span>Total due</span><span x-text="money(subtotal + tax)"></span></div>@endif
        </div>@endunless
    </div>
</div>
</div>

<div style="padding:0 28px 20px"><div class="iv-foot">
    <div style="display:flex;flex-direction:column;gap:12px"><div class="el-h">Checks</div>
        <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px 20px"><template x-for="(k, i) in checks" :key="i"><div class="iv-chk"><span :style="k.ok ? 'background:#e7f4ec;color:#1d7048' : 'background:#fbf0dc;color:#94610a'" x-text="k.ok ? '✓' : '!'"></span><div><div style="font-weight:500" x-text="k.t"></div><div style="color:#55585d" x-text="k.d"></div></div></div></template></div></div>
    <label class="iv-f"><span>Note on invoice</span><textarea name="notes" rows="3" maxlength="2000" placeholder="Optional. Printed above the terms.">{{ old('notes', $invoice?->notes) }}</textarea></label>
    <div style="display:flex;flex-direction:column;gap:8px;padding:14px 16px;border-radius:8px;background:#f8f8f9"><div class="el-h">Will be</div>
        <div style="display:flex;justify-content:space-between;gap:12px"><span class="el-sub" style="font-size:13px">Number</span><span><code>{{ $nextNumber }}</code></span></div>
        <div style="display:flex;justify-content:space-between;gap:12px"><span class="el-sub" style="font-size:13px">Due</span><span x-text="dueText"></span></div>
        <div style="display:flex;justify-content:space-between;gap:12px"><span class="el-sub" style="font-size:13px">Template</span><span>@if (Route::has('invoice-templates.index'))<a href="{{ route('invoice-templates.index') }}">{{ $template }}</a>@else{{ $template }}@endif</span></div>
        <div style="color:#55585d;line-height:1.5;text-wrap:pretty;font-size:12.5px">Ticked dispatch notes are locked to this invoice so they can't be billed twice.</div></div>
</div></div>
@include('finance::invoices._charges', ['document' => 'invoice', 'flat' => true, 'adjustments' => old('adjustments', $invoice?->adjustments ?? [])])
</div>
</form>
<script>
function invoiceLines(start, rates, products, lookup, dispatches, cfg) {
    let seq = 0;
    const blank = l => ({ ...l, key: ++seq, uom: l.uom || 'pcs', discount_amount: l.discount_amount ?? 0, product_id: l.product_id ?? '', tax_rate_id: l.tax_rate_id ?? '', supply_type: l.supply_type ?? 'GOODS', tariff_code: l.tariff_code ?? '', inventory_class: l.inventory_class ?? '', dn: l.dn ?? null, extra: l.extra ?? null, label: l.label ?? '' });
    return {
        products, cfg, dispatches, trace: false, dnOpen: true,
        lines: start.map(blank),
        date: @js(old('invoice_date', $invoice?->invoice_date?->toDateString() ?? today()->toDateString())),
        terms: @js(old('payment_terms', $invoice?->payment_terms)),
        po: @js(old('customer_reference', $head['customer_reference'] ?? '')),
        due: @js(old('due_date', $invoice?->due_date?->toDateString())),
        gross(l) { return (Number(l.quantity) || 0) * (Number(l.unit_price) || 0); },
        // A fixed amount off counts only while Disc % is 0; otherwise the amount is worked out from the percent.
        discAmt(l) { const p = Math.min(100, Math.max(0, Number(l.discount_percent) || 0)), g = this.gross(l); return p > 0 ? g * p / 100 : Math.min(g, Math.max(0, Number(l.discount_amount) || 0)); },
        net(l) { return Math.round((this.gross(l) - this.discAmt(l)) * 100) / 100; },
        lineTax(l) { return Math.round(this.net(l) * (rates[l.tax_rate_id] || 0)) / 100; },
        get discount() { return this.lines.reduce((n, l) => n + this.discAmt(l), 0); },
        // Enter in a cell moves to the same column on the next row (Tab moves across as usual).
        next(e) { const td = e.target.closest('td'), i = [...td.parentElement.children].indexOf(td); let r = td.parentElement.nextElementSibling; while (r && ! r.children[i]?.querySelector('input')) { r = r.nextElementSibling; } r?.children[i].querySelector('input')?.focus(); },
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
        trace_(l) {
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
            this.reprice(l);
        },
        // The unit price is the price for one unit of the line's UoM: changing the UoM asks the price list again.
        uoms(l) { return [...new Set(['pcs', 'kg', l.uom].filter(Boolean))]; },
        reprice(l) {
            const id = l.product_id;
            if (! lookup || ! id) { return; }
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
