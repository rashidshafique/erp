{{-- The sales order form, for a new order and for editing one: $order (null when new), $action, $title, $submit, $cancel,
     $needsReason, $started, with the catalogue data from SalesOrderController::create(). --}}
@include('partials.search-select')
@include('partials.quick-dialog', ['id' => 'qd-customer', 'title' => 'Add a customer', 'action' => route('customers.store'), 'submit' => 'Add customer', 'fields' => 'customers._quick-fields'])
@php
    $photos = \App\Models\Attachment::coverUrlsFor('product', $revisions->pluck('product_id')->merge($bareProducts->pluck('id'))->all());
    $money = \App\Support\Formats::all();
    $prices = \App\Support\OrderRules::showsPrices();
    // The discount box shows when the setting is on, or when a saved line already has a discount (so editing never hides it).
    $discounts = $prices && (\App\Domains\Sales\Services\PriceLists::discountsOn() || ($order?->lines->contains(fn ($l) => (float) $l->discount_percent > 0) ?? false));
    // Everything that can be ordered, keyed by the value a line posts: a revision id, or "product:<id>" for a product with no revision yet.
    $catalogue = $revisions->mapWithKeys(fn ($r) => [$r->id => [
        'name' => $r->product?->name, 'sub' => $r->product?->code.' · rev '.$r->revision, 'img' => $photos[$r->product_id] ?? null,
        'price' => $lastPrices[$r->product_id] ?? null, 'uom' => $r->product?->base_uom ?: 'pcs', 'kg' => $r->net_weight_kg ?? $r->product?->nominal_weight_kg,
    ]])->union($bareProducts->mapWithKeys(fn ($p) => ['product:'.$p->id => [
        'name' => $p->name, 'sub' => $p->code.' · no revision yet', 'img' => $photos[$p->id] ?? null,
        'price' => $lastPrices[$p->id] ?? null, 'uom' => $p->base_uom ?: 'pcs', 'kg' => $p->nominal_weight_kg,
    ]]));
    $existing = $order?->lines->keyBy('id') ?? collect();
    // A line already on the order shows its own product even when that revision is no longer offered.
    $fromLine = fn ($l) => ['name' => $l->product?->name, 'sub' => $l->product?->code.' · rev '.$l->productRevision?->revision, 'img' => $photos[$l->product_id] ?? null, 'price' => null, 'uom' => $l->product?->base_uom ?: 'pcs', 'kg' => $l->pieceWeightKg() ?: null];
    $blankLine = ['name' => '', 'sub' => '', 'img' => null, 'price' => null, 'uom' => 'pcs', 'kg' => null];
    $trim = fn ($v) => $v === null ? null : rtrim(rtrim((string) $v, '0'), '.');
    $formLines = old('lines') !== null
        ? collect(old('lines'))->map(function ($l) use ($existing, $catalogue, $fromLine) {
            $line = $existing[$l['id'] ?? ''] ?? null;
            $value = $l['product_revision_id'] ?? $line?->product_revision_id;

            return isset($catalogue[$value]) || $line ? ['value' => $value, 'p' => $catalogue[$value] ?? $fromLine($line), 'line' => $l, 'record' => $line] : null;
        })->filter()->values()
        : ($order?->lines ?? collect())->map(fn ($line) => ['value' => $line->product_revision_id, 'p' => $catalogue[$line->product_revision_id] ?? $fromLine($line), 'record' => $line,
            'line' => ['uom' => $line->isSoldByKg() ? 'kg' : 'piece', 'ordered_qty' => $trim($line->repeats() ? $line->repeat_qty : $line->ordered_qty), 'unit_price' => $trim($line->unit_price), 'discount_percent' => $trim($line->discount_percent), 'requested_delivery_date' => $line->requested_delivery_date?->toDateString(),
                'repeat_every' => $line->repeat_every, 'repeat_from' => $line->repeat_from?->toDateString(), 'repeat_until' => $line->repeat_until?->toDateString(),
                'staggered' => ! $line->repeats() && $line->deliveries->isNotEmpty(),
                'deliveries' => $line->repeats() ? [] : $line->deliveries->map(fn ($d) => ['id' => $d->id, 'due_date' => $d->due_date->toDateString(), 'quantity' => $trim($d->quantity), 'notes' => $d->notes])->all()]])->values();
@endphp
<style>
    .so-head { display: flex; align-items: center; gap: 14px; margin-bottom: 16px; }
    .so-head h1 { margin: 0; font-size: 24px; }
    .so-back { display: inline-flex; align-items: center; justify-content: center; width: 38px; height: 38px; border: 1px solid var(--border); border-radius: 8px; background: var(--surface); color: var(--text); text-decoration: none; font-size: 18px; }
    .so-back:hover { border-color: var(--accent); color: var(--accent); }
    .so-card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; }
    .so-body { padding: 22px 24px; }
    .so-card h2 { font-size: 17px; margin: 0 0 14px; }
    .so-top { display: grid; grid-template-columns: minmax(220px, 2fr) repeat(2, minmax(150px, 1fr)) minmax(180px, 1.3fr); gap: 14px; max-width: 1100px; }
    .so-top .field { margin: 0; }
    .so-thumb { width: 34px; height: 34px; border-radius: 6px; object-fit: cover; background: color-mix(in srgb, var(--border) 70%, transparent); display: inline-flex; align-items: center; justify-content: center; color: var(--muted); }
    .so-lines { margin-top: 14px; }
    .so-grid { display: grid; grid-template-columns: 26px 46px minmax(180px, 1fr) 70px 120px 150px 130px 34px; gap: 10px; align-items: center; }
    .so-disc .so-grid { grid-template-columns: 26px 46px minmax(180px, 1fr) 70px 120px 150px 80px 130px 34px; }
    .so-noprice .so-grid { grid-template-columns: 26px 46px minmax(180px, 1fr) 70px 120px 34px; }
    .so-lines-head { padding: 9px 8px; border: 1px solid var(--border); border-radius: 8px 8px 0 0; background: color-mix(in srgb, var(--border) 35%, transparent); color: var(--muted); font-size: 12.5px; font-weight: 600; text-transform: uppercase; letter-spacing: .03em; }
    .so-lines-head .so-head-product { grid-column: 1 / 4; padding-left: 4px; }
    .so-lines-head .so-right { text-align: right; }
    .so-lines [data-lines] { border: 1px solid var(--border); border-top: 0; border-radius: 0 0 8px 8px; padding: 0 8px; }
    .so-lines [data-lines] .so-line:last-child { border-bottom: 0; }
    .so-line { border-bottom: 1px solid var(--border); padding: 10px 0; }
    .so-line .so-thumb { width: 46px; height: 46px; }
    .so-caret { border: 0; background: none; color: var(--accent); cursor: pointer; font-size: 13px; padding: 4px; transition: transform .15s; }
    .so-line.is-open .so-caret { transform: rotate(90deg); }
    .so-prod strong { display: block; font-weight: 500; }
    .so-prod small { color: var(--muted); }
    .so-money { position: relative; }
    .so-money input { width: 100%; padding-right: 48px; }
    .so-money span { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 13px; pointer-events: none; }
    .so-qty { width: 100%; }
    .so-qty input { width: 100%; }
    .so-qty small { display: block; margin-top: 2px; color: var(--muted); font-size: 12px; }
    .so-uom { color: var(--muted); font-size: 13px; }
    .so-uom select { width: 100%; padding-left: 6px; padding-right: 4px; }
    .so-total { text-align: right; font-variant-numeric: tabular-nums; }
    .so-remove { width: 30px; height: 30px; border: 0; border-radius: 50%; background: none; color: var(--muted); font-size: 18px; cursor: pointer; }
    .so-remove:hover { background: color-mix(in srgb, var(--danger, #b42318) 12%, transparent); color: var(--danger, #b42318); }
    .so-extra { margin: 10px 0 2px 82px; display: flex; gap: 14px; flex-wrap: wrap; align-items: end; }
    .so-extra .field { margin: 0; }
    .so-extra[hidden], .so-delivery-foot[hidden], .so-preview[hidden] { display: none; }
    .so-check { display: inline-flex; align-items: center; gap: 6px; min-height: 38px; margin: 0; font-weight: 500; cursor: pointer; }
    .so-deliveries { margin: 10px 0 2px 82px; max-width: 640px; }
    .so-delivery { display: grid; grid-template-columns: 160px 120px minmax(120px, 1fr) 34px; gap: 8px; align-items: center; margin-bottom: 6px; }
    .so-delivery input { width: 100%; }
    .so-delivery-foot { display: flex; gap: 12px; align-items: center; }
    .so-delivery-foot .is-over { color: var(--danger, #b42318); font-weight: 600; }
    .so-preview { margin: 8px 0 2px 82px; padding: 8px 12px; border-radius: 8px; background: color-mix(in srgb, var(--accent) 8%, transparent); font-size: 13.5px; }
    .so-preview.is-jump { background: color-mix(in srgb, var(--danger, #b42318) 10%, transparent); }
    .so-preview strong { font-variant-numeric: tabular-nums; }
    .so-prod select { width: 100%; }
    .so-prod select + small { display: block; margin-top: 2px; }
    .so-add-row { display: flex; gap: 14px; align-items: center; margin-top: 12px; }
    .so-empty { padding: 26px 0; text-align: center; color: var(--muted); border-bottom: 1px solid var(--border); }
    .so-bottom { display: grid; grid-template-columns: minmax(0, 1fr) minmax(260px, 380px); gap: 24px; margin-top: 22px; align-items: start; }
    .so-bottom textarea { width: 100%; max-width: 480px; }
    .so-summary { border: 1px solid var(--border); border-radius: 10px; padding: 16px 20px; background: color-mix(in srgb, var(--border) 25%, transparent); }
    .so-summary div { display: flex; justify-content: space-between; padding: 4px 0; font-variant-numeric: tabular-nums; }
    .so-summary .so-grand { font-size: 20px; font-weight: 700; padding-top: 10px; }
    .so-foot { display: flex; gap: 10px; padding: 16px 24px; border-top: 1px solid var(--border); }
    @media (max-width: 900px) {
        .so-top { grid-template-columns: 1fr; }
        .so-grid, .so-disc .so-grid { grid-template-columns: 22px 1fr 110px 34px; row-gap: 8px; }
        .so-line .so-pct { grid-column: 3; }
        .so-lines-head { display: none; }
        .so-lines [data-lines] { border-top: 1px solid var(--border); border-radius: 8px; }
        .so-line .so-thumb { display: none; }
        .so-line .so-uom { grid-column: 2; }
        .so-line .so-prod { grid-column: 2 / 4; }
        .so-line .so-money { grid-column: 2; }
        .so-line .so-qty { grid-column: 3; }
        .so-line .so-total { grid-column: 2 / 4; }
        .so-extra, .so-preview, .so-deliveries { margin-left: 32px; }
        .so-delivery { grid-template-columns: 1fr 90px 34px; }
        .so-delivery input[name$="[notes]"] { grid-column: 1 / 3; grid-row: 2; }
        .so-bottom { grid-template-columns: 1fr; }
    }
</style>

<div class="so-head"><a class="so-back" href="{{ $cancel }}" aria-label="Back">&larr;</a><div><h1>{{ $title }}</h1>@if ($order)<p class="muted small" style="margin:2px 0 0">Every change is written to the order's history with who made it{{ $needsReason ? ' and your reason' : '' }}.</p>@endif</div></div>

@if ($errors->any())<div class="alert alert-error">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif

@if ($catalogue->isEmpty())
    <div class="alert alert-error">There are no products to order yet. Add a product first.</div>
@else
<form method="POST" action="{{ $action }}" id="so-form" @class(['so-noprice' => ! $prices, 'so-disc' => $discounts]) data-today="{{ today()->toDateString() }}" data-ahead="{{ \App\Support\OrderRules::repeatAheadDays() }}">
@csrf @if ($order) @method('PUT') @endif
<div class="so-card"><div class="so-body">
    <h2>Order detail</h2>
    <div class="so-top">
        <div class="field"><label for="customer_id">Customer <a href="{{ route('customers.create') }}" class="qd-add" data-quick="qd-customer" data-quick-target="customer_id">+ Add</a></label>
            <select id="customer_id" name="customer_id" required data-search-select><option value="">Select customer</option>
                @foreach ($customers as $c)<option value="{{ $c->id }}" data-sub="{{ $c->code }}" @selected(old('customer_id', $order?->customer_id) === $c->id)>{{ $c->name }}</option>@endforeach
            </select>@if ($started)<div class="muted small">Production orders exist, so the customer cannot change.</div>@endif</div>
        <div class="field"><label for="order_date">Order date</label><input id="order_date" name="order_date" type="date" required value="{{ old('order_date', $order?->order_date?->toDateString() ?? today()->toDateString()) }}"></div>
        <div class="field"><label for="requested_delivery_date">Requested delivery</label><input id="requested_delivery_date" name="requested_delivery_date" type="date" value="{{ old('requested_delivery_date', $order?->requested_delivery_date?->toDateString()) }}"></div>
        <div class="field"><label for="customer_reference">Customer PO / reference</label><input id="customer_reference" name="customer_reference" maxlength="100" value="{{ old('customer_reference', $order?->customer_reference) }}"></div>
    </div>

    <div class="so-lines">
        <div class="so-grid so-lines-head" aria-hidden="true"><span class="so-head-product">Product</span><span>Unit</span><span>Qty</span>@if ($prices)<span>Price</span>@if ($discounts)<span>Disc %</span>@endif<span class="so-right">Total</span>@endif<span></span></div>
        <div data-lines>
            @foreach ($formLines as $i => $l)
                @include('sales::orders._create-line', ['discounts' => $discounts, 'i' => $i, 'value' => $l['value'], 'p' => $l['p'], 'line' => $l['line'], 'record' => $l['record'], 'catalogue' => $catalogue, 'currency' => $money['currency'], 'prices' => $prices])
            @endforeach
            {{-- A new order starts with one empty row to pick the first product in. --}}
            @if ($formLines->isEmpty() && ! $order)
                @include('sales::orders._create-line', ['discounts' => $discounts, 'i' => 0, 'value' => '', 'p' => $blankLine, 'line' => [], 'record' => null, 'catalogue' => $catalogue, 'currency' => $money['currency'], 'prices' => $prices])
            @endif
        </div>
        <div class="so-empty" data-no-lines hidden>No lines yet. Add the first one below.</div>
        <div class="so-add-row"><button type="button" class="btn" data-add-line>+ Add line</button><a href="{{ route('products.create') }}" target="_blank" class="qd-add">+ Create product</a></div>
    </div>

    <div class="so-bottom">
        <div class="field"><label for="notes">Notes</label><textarea id="notes" name="notes" rows="3" maxlength="3000" placeholder="Type here">{{ old('notes', $order?->notes) }}</textarea>
            @if ($needsReason)<div class="field" style="margin-top:14px"><label for="reason">Reason for the change</label><input id="reason" name="reason" required minlength="5" maxlength="300" value="{{ old('reason') }}" placeholder="Why is the order being changed?"></div>@endif</div>
        <div class="so-summary" aria-live="polite">
            <div><span>Lines</span><span data-count>0</span></div>
            <div><span>Pieces</span><span data-pieces-total>0</span></div>
            <div data-kg-row hidden><span>Kg</span><span data-kg-total>0</span></div>
            @if ($prices)<div class="so-grand"><span>Total</span><span data-grand>—</span></div>@endif
        </div>
    </div>
</div>
<div class="so-foot">@error('confirm_jump')<label style="margin:0 8px 0 0;align-self:center"><input type="checkbox" name="confirm_jump" value="1"> Yes, these quantities are right</label>@enderror<button class="btn btn-primary" type="submit">{{ $submit }}</button><a class="btn" href="{{ $cancel }}">Cancel</a></div>
</div>
</form>

<template id="so-line-template">@include('sales::orders._create-line', ['discounts' => $discounts, 'i' => '__I__', 'value' => '', 'p' => $blankLine, 'line' => [], 'record' => null, 'catalogue' => $catalogue, 'currency' => $money['currency'], 'prices' => $prices])</template>

<script>
(function () {
    var form = document.getElementById('so-form'), linesBox = form.querySelector('[data-lines]'), tpl = document.getElementById('so-line-template');
    var next = linesBox.children.length, money = @json(['currency' => $money['currency'], 'before' => $money['currency_position'] === 'before']);
    function fmt(n) {
        var s = n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        return money.currency ? (money.before ? money.currency + ' ' + s : s + ' ' + money.currency) : s;
    }
    function recalc() {
        var total = 0, pieces = 0, kgs = 0, rows = [].filter.call(linesBox.querySelectorAll('[data-line]'), chosen);
        rows.forEach(function (row) {
            var q = row.dataset.pieces ? parseFloat(row.dataset.pieces) : (parseFloat(row.querySelector('[data-qty]').value) || 0), priceBox = row.querySelector('[data-price]'), p = priceBox ? parseFloat(priceBox.value) : NaN, discBox = row.querySelector('[data-disc]'), d = discBox ? Math.min(100, Math.max(0, parseFloat(discBox.value) || 0)) : 0;
            if (!isNaN(p)) p = p * (1 - d / 100);
            if (byKg(row)) kgs += q; else pieces += q;
            if (priceBox) row.querySelector('[data-total]').textContent = isNaN(p) ? '—' : fmt(q * p);
            if (!isNaN(p)) total += q * p;
        });
        form.querySelector('[data-count]').textContent = rows.length;
        form.querySelector('[data-pieces-total]').textContent = pieces.toLocaleString();
        form.querySelector('[data-kg-total]').textContent = num(kgs);
        form.querySelector('[data-kg-row]').hidden = !kgs;
        if (form.querySelector('[data-grand]')) form.querySelector('[data-grand]').textContent = rows.length ? fmt(total) : '—';
        form.querySelector('[data-no-lines]').hidden = linesBox.querySelector('[data-line]') !== null;
    }
    // The repeat preview: works out the same deliveries the server will schedule, so the line's new total is seen before saving.
    function day(s) { var p = (s || '').split('-'); return p.length === 3 ? new Date(+p[0], +p[1] - 1, +p[2]) : null; }
    function step(from, n, every) {
        if (every === 'WEEK') return new Date(from.getFullYear(), from.getMonth(), from.getDate() + 7 * n);
        var last = new Date(from.getFullYear(), from.getMonth() + n + 1, 0).getDate();
        return new Date(from.getFullYear(), from.getMonth() + n, Math.min(from.getDate(), last));
    }
    function short(d) { return d.toLocaleDateString(undefined, { day: 'numeric', month: 'short' }); }
    function num(n) { return n.toLocaleString(undefined, { maximumFractionDigits: 3 }); }
    function preview(row) {
        var pick = row.querySelector('[data-repeat]'), out = row.querySelector('[data-repeat-preview]'), qty = parseFloat(row.querySelector('[data-qty]').value) || 0;
        row.querySelector('[data-qty]').placeholder = pick.value ? (pick.value === 'WEEK' ? 'Each week' : 'Each month') : 'Qty';
        if (!pick.value) { out.hidden = true; row.dataset.pieces = ''; return; }
        var field = function (n) { var el = row.querySelector('[name$="[' + n + ']"]'); return el ? el.value : ''; };
        var from = day(field('repeat_from') || field('requested_delivery_date') || form.querySelector('#requested_delivery_date').value || form.querySelector('#order_date').value);
        var until = day(field('repeat_until')), today = day(form.dataset.today), horizon = new Date(today.getFullYear(), today.getMonth(), today.getDate() + (+form.dataset.ahead));
        if (!from) { out.hidden = true; return; }
        var was = row.dataset.wasRepeating === '1', kept = parseFloat(row.dataset.kept) || 0, ordered = parseFloat(row.dataset.ordered) || 0;
        var force = !was || from >= today, now = [], all = 0;
        for (var n = 0; n < 600; n++) {
            var d = step(from, n, pick.value);
            if (until && d > until) break;
            if (was && d < today) continue;
            if (d <= horizon || (force && !now.length)) now.push(d);
            if (!until && d > horizon) break;
            all++;
        }
        var total = kept + now.length * qty, every = pick.value === 'WEEK' ? 'week' : 'month';
        var text = '<strong>' + num(qty) + '</strong> each ' + every + ': ' + now.length + ' ' + (now.length === 1 ? 'delivery' : 'deliveries')
            + (now.length ? ' (' + short(now[0]) + (now.length > 1 ? ' to ' + short(now[now.length - 1]) : '') + ')' : '')
            + (kept ? ' + ' + num(kept) + (was ? ' already due' : ' already shipped') : '') + ' = <strong>' + num(total) + '</strong> ordered now';
        text += until ? ', <strong>' + num(kept + all * qty) + '</strong> by ' + short(until) + '.' : ', then ' + num(qty) + ' more each ' + every + '.';
        var jump = ordered > 0 && (total > ordered * 3 || total < ordered / 3);
        if (jump) text += ' This line is ' + num(ordered) + ' now: please check the quantity is for each ' + every + '.';
        out.innerHTML = text; out.hidden = false; out.classList.toggle('is-jump', jump);
        row.dataset.pieces = String(total);
    }
    function previewAll() { linesBox.querySelectorAll('[data-line]').forEach(preview); }
    // Staggered deliveries (saved lines only): shown on a one-off line with the box ticked; hidden rows are disabled so they are not sent or required.
    var nextDelivery = 1000;
    function stagger(row) {
        var pick = row.querySelector('[data-repeat]'), tick = row.querySelector('[data-stagger]'), box = row.querySelector('[data-deliveries]');
        if (!tick) return;
        var on = !pick.value && tick.checked;
        row.querySelector('[data-stagger-field]').hidden = !!pick.value;
        box.hidden = !on;
        box.querySelectorAll('[data-delivery-rows] input').forEach(function (i) { i.disabled = !on; });
        if (on && !box.querySelector('[data-delivery-row]')) addDelivery(row);
        unscheduled(row);
    }
    function addDelivery(row) {
        var holder = document.createElement('div');
        holder.innerHTML = row.querySelector('[data-delivery-template]').innerHTML.replace(/__D__/g, String(nextDelivery++)).trim();
        var added = holder.firstElementChild, qty = added.querySelector('[data-delivery-qty]'), left = parseFloat(row.dataset.unscheduled);
        if (left > 0) qty.value = left;
        row.querySelector('[data-delivery-rows]').appendChild(added);
        unscheduled(row);
        added.querySelector('input[type=date]').focus();
    }
    function unscheduled(row) {
        var out = row.querySelector('[data-unscheduled]');
        if (!out) return;
        var ordered = parseFloat(row.querySelector('[data-qty]').value) || 0, scheduled = 0;
        row.querySelectorAll('[data-delivery-qty]').forEach(function (i) { scheduled += parseFloat(i.value) || 0; });
        var left = Math.round((ordered - scheduled) * 1000) / 1000;
        row.dataset.unscheduled = String(left);
        out.textContent = left < 0 ? 'Over the ordered ' + num(ordered) + ' by ' + num(-left) : 'Still to schedule: ' + num(left) + ' of ' + num(ordered);
        out.classList.toggle('is-over', left < 0);
    }
    // A line sold by kg shows the whole pieces its quantity needs, from the casting's weight.
    function byKg(row) { var u = row.querySelector('[data-unit]'); return !!(u && u.value === 'kg'); }
    function piecesHint(row) {
        var out = row.querySelector('[data-pcs-hint]'), each = parseFloat(row.dataset.eachKg) || 0, q = parseFloat(row.querySelector('[data-qty]').value) || 0;
        if (!out) return;
        out.hidden = !byKg(row);
        out.textContent = !each ? 'No casting weight' : (q ? '≈ ' + Math.ceil(Math.round(q / each * 1e6) / 1e6).toLocaleString() + ' pcs' : num(each) + ' kg each');
    }
    // A row counts once a product is picked; an empty row is left out of the totals and not sent.
    function chosen(row) { var p = row.querySelector('[name$="[product_revision_id]"]'); return !!(p && p.value); }
    function addLine() {
        var holder = document.createElement('div'); holder.innerHTML = tpl.innerHTML.replace(/__I__/g, String(next++)).trim();
        var row = holder.firstElementChild;
        linesBox.appendChild(row); recalc();
        row.querySelector('[data-product]').dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
    }
    function pickProduct(row, select) {
        var o = select.options[select.selectedIndex], d = o ? o.dataset : {}, thumb = row.querySelector('.so-thumb'), priceBox = row.querySelector('[data-price]');
        row.querySelector('[data-sub]').textContent = d.sub || '';
        row.dataset.eachKg = d.kg || '';
        var unit = row.querySelector('[data-unit]');
        if (unit && select.value) unit.value = (d.uom || '').toLowerCase() === 'kg' ? 'kg' : 'piece';
        piecesHint(row);
        row.querySelector('[data-qty]').required = !!select.value;
        if (priceBox && d.price && !priceBox.value) { priceBox.value = d.price; priceBox.dataset.filled = priceBox.value; }
        listPrice(row);
        var img = d.img ? document.createElement('img') : document.createElement('span');
        img.className = 'so-thumb';
        if (d.img) { img.src = d.img; img.alt = ''; } else { img.setAttribute('aria-hidden', 'true'); img.innerHTML = '&#9638;'; }
        thumb.replaceWith(img);
        if (select.value) row.querySelector('[data-qty]').focus();
    }
    // The price list's price for the line, filled in unless someone has typed their own price over what was filled in before.
    function listPrice(row) {
        var priceBox = row.querySelector('[data-price]'), product = row.querySelector('[name$="[product_revision_id]"]'), unit = row.querySelector('[data-unit]');
        if (!priceBox || !product || !product.value || (priceBox.value !== '' && priceBox.value !== priceBox.dataset.filled)) return;
        var q = new URLSearchParams({ customer: form.querySelector('#customer_id').value, product: product.value, qty: row.querySelector('[data-qty]').value || 0,
            uom: unit ? unit.value : 'piece', date: form.querySelector('#order_date').value });
        fetch(@js(route('price-lists.lookup')) + '?' + q, { headers: { Accept: 'application/json' } }).then(function (r) { return r.ok ? r.json() : { price: null }; }).then(function (j) {
            if (j.price === null || (priceBox.value !== '' && priceBox.value !== priceBox.dataset.filled)) return;
            priceBox.value = String(j.price); priceBox.dataset.filled = priceBox.value; priceBox.title = 'Price list ' + j.list; recalc();
        }).catch(function () {});
    }
    form.querySelector('#customer_id').addEventListener('change', function () { linesBox.querySelectorAll('[data-line]').forEach(listPrice); });
    form.querySelector('[data-add-line]').addEventListener('click', addLine);
    linesBox.addEventListener('input', function (e) { var row = e.target.closest('[data-line]'); if (row) { preview(row); unscheduled(row); piecesHint(row); } recalc(); });
    linesBox.addEventListener('change', function (e) {
        var row = e.target.closest('[data-line]'); if (!row) return;
        if (e.target.matches('[data-product]')) pickProduct(row, e.target);
        var pick = e.target.closest('[data-repeat]');
        if (pick) {
            row.querySelectorAll('[data-repeat-field]').forEach(function (el) { el.hidden = !pick.value; });
            row.querySelector('[data-repeat-note]').hidden = !!pick.value;
        }
        if (pick || e.target.closest('[data-stagger]')) stagger(row);
        if (e.target.matches('[data-qty], [data-unit]')) listPrice(row);
        piecesHint(row); preview(row); recalc();
    });
    ['order_date', 'requested_delivery_date'].forEach(function (id) { form.querySelector('#' + id).addEventListener('change', function () { previewAll(); recalc(); }); });
    linesBox.addEventListener('click', function (e) {
        var row = e.target.closest('[data-line]'); if (!row) return;
        if (e.target.closest('[data-remove]')) { row.remove(); recalc(); return; }
        if (e.target.closest('[data-add-delivery]')) { addDelivery(row); return; }
        if (e.target.closest('[data-remove-delivery]')) { e.target.closest('[data-delivery-row]').remove(); unscheduled(row); return; }
        if (e.target.closest('[data-toggle]')) {
            var open = row.classList.toggle('is-open');
            row.querySelector('[data-extra]').hidden = !open;
            e.target.closest('[data-toggle]').setAttribute('aria-expanded', open ? 'true' : 'false');
        }
    });
    form.addEventListener('submit', function (e) {
        if (![].some.call(linesBox.querySelectorAll('[data-line]'), chosen)) { e.preventDefault(); alert('Pick a product on at least one line.'); }
    });
    previewAll();
    linesBox.querySelectorAll('[data-line]').forEach(function (row) { unscheduled(row); piecesHint(row); });
    recalc();
})();
</script>
@endif
