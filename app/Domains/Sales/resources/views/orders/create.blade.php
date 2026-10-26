@extends('layouts.app')

@section('title', 'New sales order')
@section('breadcrumb', 'Sales orders / New')

@section('content')
@include('partials.search-select')
@include('partials.quick-dialog', ['id' => 'qd-customer', 'title' => 'Add a customer', 'action' => route('customers.store'), 'submit' => 'Add customer', 'fields' => 'customers._quick-fields'])
@php
    $photos = \App\Models\Attachment::coverUrlsFor('product', $revisions->pluck('product_id')->merge($bareProducts->pluck('id'))->all());
    $money = \App\Support\Formats::all();
    // Everything that can be ordered, keyed by the value a line posts: a revision id, or "product:<id>" for a product with no revision yet.
    $catalogue = $revisions->mapWithKeys(fn ($r) => [$r->id => [
        'name' => $r->product?->name, 'sub' => $r->product?->code.' · rev '.$r->revision, 'img' => $photos[$r->product_id] ?? null,
        'price' => $lastPrices[$r->product_id] ?? null, 'uom' => $r->product?->base_uom ?: 'pcs', 'kg' => $r->net_weight_kg ?? $r->product?->nominal_weight_kg,
    ]])->union($bareProducts->mapWithKeys(fn ($p) => ['product:'.$p->id => [
        'name' => $p->name, 'sub' => $p->code.' · no revision yet', 'img' => $photos[$p->id] ?? null,
        'price' => $lastPrices[$p->id] ?? null, 'uom' => $p->base_uom ?: 'pcs', 'kg' => $p->nominal_weight_kg,
    ]]));
    $oldLines = collect(old('lines', []))->filter(fn ($l) => isset($catalogue[$l['product_revision_id'] ?? '']))->values();
@endphp
<style>
    .so-head { display: flex; align-items: center; gap: 14px; margin-bottom: 16px; }
    .so-head h1 { margin: 0; font-size: 24px; }
    .so-back { display: inline-flex; align-items: center; justify-content: center; width: 38px; height: 38px; border: 1px solid var(--border); border-radius: 8px; background: var(--surface); color: var(--text); text-decoration: none; font-size: 18px; }
    .so-back:hover { border-color: var(--accent); color: var(--accent); }
    .so-card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; }
    .so-body { padding: 22px 24px; }
    .so-card h2 { font-size: 17px; margin: 0 0 14px; }
    .so-top { display: grid; grid-template-columns: minmax(220px, 2fr) repeat(2, minmax(150px, 1fr)); gap: 14px; max-width: 900px; }
    .so-top .field { margin: 0; }
    .so-more { margin: 16px 0 4px; }
    .so-more summary { cursor: pointer; color: var(--accent); font-weight: 500; list-style: none; display: inline-flex; align-items: center; gap: 8px; }
    .so-more summary::before { content: "▸"; font-size: 12px; transition: transform .15s; }
    .so-more[open] summary::before { transform: rotate(90deg); }
    .so-more .so-top { margin-top: 12px; }
    .so-picker { position: relative; display: flex; gap: 10px; margin-top: 20px; padding: 10px; border-radius: 10px; background: color-mix(in srgb, var(--border) 45%, transparent); }
    .so-search { flex: 1; position: relative; }
    .so-search input { width: 100%; min-height: 42px; padding-left: 38px; font-size: 15px; }
    .so-search svg { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--muted); pointer-events: none; }
    .so-browse { min-height: 42px; white-space: nowrap; }
    .so-results { position: absolute; left: 10px; right: 10px; top: calc(100% - 6px); z-index: 30; background: var(--surface); border: 1px solid var(--border); border-radius: 10px; box-shadow: 0 16px 36px rgb(15 23 42 / 18%); overflow: hidden; }
    .so-results-head { display: flex; justify-content: space-between; align-items: center; padding: 10px 16px; border-bottom: 1px solid var(--border); background: color-mix(in srgb, var(--border) 25%, transparent); }
    .so-results-head a { color: var(--accent); font-weight: 500; text-decoration: none; }
    .so-results-list { max-height: 320px; overflow-y: auto; }
    .so-item { display: grid; grid-template-columns: 34px 1fr auto auto; gap: 12px; align-items: center; width: 100%; padding: 8px 16px; border: 0; border-bottom: 1px solid var(--border); background: none; color: var(--text); text-align: left; font: inherit; cursor: pointer; }
    .so-item:last-child { border-bottom: 0; }
    .so-item[aria-selected="true"], .so-item:hover { background: color-mix(in srgb, var(--accent) 10%, transparent); }
    .so-item .so-sub { color: var(--muted); font-size: 13px; }
    .so-item .so-price { color: var(--success, #15803d); font-weight: 600; font-size: 13px; min-width: 90px; text-align: right; }
    .so-thumb { width: 34px; height: 34px; border-radius: 6px; object-fit: cover; background: color-mix(in srgb, var(--border) 70%, transparent); display: inline-flex; align-items: center; justify-content: center; color: var(--muted); }
    .so-lines { margin-top: 14px; }
    .so-grid { display: grid; grid-template-columns: 26px 46px minmax(180px, 1fr) 150px 18px 110px 46px 130px 34px; gap: 10px; align-items: center; }
    .so-lines-head { padding: 0 0 8px; border-bottom: 1px solid var(--border); color: var(--muted); font-size: 13.5px; }
    .so-lines-head .so-right { text-align: right; }
    .so-line { border-bottom: 1px solid var(--border); padding: 10px 0; }
    .so-line .so-thumb { width: 46px; height: 46px; }
    .so-caret { border: 0; background: none; color: var(--accent); cursor: pointer; font-size: 13px; padding: 4px; transition: transform .15s; }
    .so-line.is-open .so-caret { transform: rotate(90deg); }
    .so-prod strong { display: block; font-weight: 500; }
    .so-prod small { color: var(--muted); }
    .so-money { position: relative; }
    .so-money input { width: 100%; padding-right: 48px; }
    .so-money span { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 13px; pointer-events: none; }
    .so-times { color: var(--muted); text-align: center; }
    .so-qty { width: 100%; }
    .so-uom { color: var(--muted); font-size: 13px; }
    .so-total { text-align: right; font-variant-numeric: tabular-nums; }
    .so-remove { width: 30px; height: 30px; border: 0; border-radius: 50%; background: none; color: var(--muted); font-size: 18px; cursor: pointer; }
    .so-remove:hover { background: color-mix(in srgb, var(--danger, #b42318) 12%, transparent); color: var(--danger, #b42318); }
    .so-extra { margin: 10px 0 2px 82px; display: flex; gap: 14px; flex-wrap: wrap; align-items: end; }
    .so-extra .field { margin: 0; }
    .so-empty { padding: 26px 0; text-align: center; color: var(--muted); border-bottom: 1px solid var(--border); }
    .so-bottom { display: grid; grid-template-columns: minmax(0, 1fr) minmax(260px, 380px); gap: 24px; margin-top: 22px; align-items: start; }
    .so-bottom textarea { width: 100%; max-width: 480px; }
    .so-summary { border: 1px solid var(--border); border-radius: 10px; padding: 16px 20px; background: color-mix(in srgb, var(--border) 25%, transparent); }
    .so-summary div { display: flex; justify-content: space-between; padding: 4px 0; font-variant-numeric: tabular-nums; }
    .so-summary .so-grand { font-size: 20px; font-weight: 700; padding-top: 10px; }
    .so-foot { display: flex; gap: 10px; padding: 16px 24px; border-top: 1px solid var(--border); }
    @media (max-width: 900px) {
        .so-top { grid-template-columns: 1fr; }
        .so-grid { grid-template-columns: 22px 1fr 110px 34px; row-gap: 8px; }
        .so-lines-head { display: none; }
        .so-line .so-thumb, .so-times, .so-uom { display: none; }
        .so-line .so-prod { grid-column: 2 / 4; }
        .so-line .so-money { grid-column: 2; }
        .so-line .so-qty { grid-column: 3; }
        .so-line .so-total { grid-column: 2 / 4; }
        .so-extra { margin-left: 32px; }
        .so-bottom { grid-template-columns: 1fr; }
    }
</style>

<div class="so-head"><a class="so-back" href="{{ route('sales-orders.index') }}" aria-label="Back to sales orders">&larr;</a><h1>New sales order</h1></div>

@if ($errors->any())<div class="alert alert-error">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif

@if ($catalogue->isEmpty())
    <div class="alert alert-error">There are no products to order yet. Add a product first.</div>
@else
<form method="POST" action="{{ route('sales-orders.store') }}" id="so-form">
@csrf
<div class="so-card"><div class="so-body">
    <h2>Order detail</h2>
    <div class="so-top">
        <div class="field"><label for="customer_id">Customer <a href="{{ route('customers.create') }}" class="qd-add" data-quick="qd-customer" data-quick-target="customer_id">+ Add</a></label>
            <select id="customer_id" name="customer_id" required data-search-select><option value="">Select customer</option>
                @foreach ($customers as $c)<option value="{{ $c->id }}" data-sub="{{ $c->code }}" @selected(old('customer_id') === $c->id)>{{ $c->name }}</option>@endforeach
            </select></div>
        <div class="field"><label for="order_date">Order date</label><input id="order_date" name="order_date" type="date" required value="{{ old('order_date', today()->toDateString()) }}"></div>
        <div class="field"><label for="requested_delivery_date">Requested delivery</label><input id="requested_delivery_date" name="requested_delivery_date" type="date" value="{{ old('requested_delivery_date') }}"></div>
    </div>
    <details class="so-more" @if (old('customer_reference')) open @endif>
        <summary>More information</summary>
        <div class="so-top">
            <div class="field"><label for="customer_reference">Customer PO / reference</label><input id="customer_reference" name="customer_reference" maxlength="100" value="{{ old('customer_reference') }}"></div>
        </div>
    </details>

    <div class="so-picker" data-picker>
        <div class="so-search">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input type="search" id="so-find" placeholder="Type to add product" autocomplete="off" aria-label="Type to add a product" aria-controls="so-results" aria-expanded="false">
        </div>
        <button type="button" class="btn so-browse" data-browse>Browse products</button>
        <div class="so-results" id="so-results" role="listbox" hidden>
            <div class="so-results-head"><a href="{{ route('products.create') }}" target="_blank">+ Create product</a><span class="muted small">Enter adds the highlighted product</span></div>
            <div class="so-results-list">
                @foreach ($catalogue as $value => $p)
                    <button type="button" class="so-item" role="option" value="{{ $value }}" data-name="{{ $p['name'] }}" data-sub="{{ $p['sub'] }}" data-img="{{ $p['img'] }}" data-price="{{ $p['price'] }}" data-uom="{{ $p['uom'] }}">
                        @if ($p['img'])<img class="so-thumb" src="{{ $p['img'] }}" alt="" loading="lazy">@else<span class="so-thumb" aria-hidden="true">&#9638;</span>@endif
                        <span>{{ $p['name'] }}</span>
                        <span class="so-sub">{{ $p['sub'] }}@if ($p['kg']) · {{ rtrim(rtrim(number_format((float) $p['kg'], 2), '0'), '.') }} kg @endif</span>
                        <span class="so-price">@if ($p['price'] !== null)last {{ \App\Support\Formats::money($p['price']) }}@endif</span>
                    </button>
                @endforeach
                <div class="so-empty" data-no-match hidden>No product matches.</div>
            </div>
        </div>
    </div>

    <div class="so-lines">
        <div class="so-grid so-lines-head"><span></span><span></span><span>Product</span><span>Price</span><span></span><span>Quantity</span><span></span><span class="so-right">Total</span><span></span></div>
        <div data-lines>
            @foreach ($oldLines as $i => $l)
                @include('sales::orders._create-line', ['i' => $i, 'value' => $l['product_revision_id'], 'p' => $catalogue[$l['product_revision_id']], 'line' => $l, 'currency' => $money['currency']])
            @endforeach
        </div>
        <div class="so-empty" data-no-lines @if ($oldLines->isNotEmpty()) hidden @endif>No products yet. Type above to add the first one.</div>
    </div>

    <div class="so-bottom">
        <div class="field"><label for="notes">Notes</label><textarea id="notes" name="notes" rows="3" maxlength="3000" placeholder="Type here">{{ old('notes') }}</textarea></div>
        <div class="so-summary" aria-live="polite">
            <div><span>Lines</span><span data-count>0</span></div>
            <div><span>Pieces</span><span data-pieces>0</span></div>
            <div class="so-grand"><span>Total</span><span data-grand>—</span></div>
        </div>
    </div>
</div>
<div class="so-foot"><button class="btn btn-primary" type="submit">Create order</button><a class="btn" href="{{ route('sales-orders.index') }}">Cancel</a></div>
</div>
</form>

<template id="so-line-template">@include('sales::orders._create-line', ['i' => '__I__', 'value' => '', 'p' => ['name' => '', 'sub' => '', 'img' => null, 'price' => null, 'uom' => 'pcs'], 'line' => [], 'currency' => $money['currency']])</template>

<script>
(function () {
    var form = document.getElementById('so-form'), find = document.getElementById('so-find'), results = document.getElementById('so-results');
    var linesBox = form.querySelector('[data-lines]'), tpl = document.getElementById('so-line-template'), items = [].slice.call(results.querySelectorAll('.so-item'));
    var next = linesBox.children.length, active = -1, money = @json(['currency' => $money['currency'], 'before' => $money['currency_position'] === 'before']);
    function fmt(n) {
        var s = n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        return money.currency ? (money.before ? money.currency + ' ' + s : s + ' ' + money.currency) : s;
    }
    function shown() { return items.filter(function (b) { return !b.hidden; }); }
    function highlight(i) {
        var list = shown(); items.forEach(function (b) { b.setAttribute('aria-selected', 'false'); });
        active = list.length ? Math.max(0, Math.min(i, list.length - 1)) : -1;
        if (active >= 0) { list[active].setAttribute('aria-selected', 'true'); list[active].scrollIntoView({ block: 'nearest' }); }
    }
    function filter() {
        var words = find.value.toLowerCase().split(/\s+/).filter(Boolean), any = false;
        items.forEach(function (b) {
            var text = (b.dataset.name + ' ' + b.dataset.sub).toLowerCase();
            b.hidden = !words.every(function (w) { return text.indexOf(w) >= 0; });
            any = any || !b.hidden;
        });
        results.querySelector('[data-no-match]').hidden = any;
        highlight(0);
    }
    function open() { results.hidden = false; find.setAttribute('aria-expanded', 'true'); filter(); }
    function close() { results.hidden = true; find.setAttribute('aria-expanded', 'false'); }
    function recalc() {
        var total = 0, pieces = 0, rows = linesBox.querySelectorAll('[data-line]');
        rows.forEach(function (row) {
            var q = parseFloat(row.querySelector('[data-qty]').value) || 0, p = parseFloat(row.querySelector('[data-price]').value);
            pieces += q;
            row.querySelector('[data-total]').textContent = isNaN(p) ? '—' : fmt(q * p);
            if (!isNaN(p)) total += q * p;
        });
        form.querySelector('[data-count]').textContent = rows.length;
        form.querySelector('[data-pieces]').textContent = pieces.toLocaleString();
        form.querySelector('[data-grand]').textContent = rows.length ? fmt(total) : '—';
        form.querySelector('[data-no-lines]').hidden = rows.length > 0;
    }
    function add(button) {
        var html = tpl.innerHTML.replace(/__I__/g, String(next++));
        var holder = document.createElement('div'); holder.innerHTML = html.trim();
        var row = holder.firstElementChild, d = button.dataset;
        row.querySelector('[data-value]').value = button.value;
        row.querySelector('[data-name]').textContent = d.name;
        row.querySelector('[data-sub]').textContent = d.sub;
        row.querySelector('[data-uom]').textContent = d.uom;
        if (d.price) row.querySelector('[data-price]').value = d.price;
        if (d.img) { var img = document.createElement('img'); img.className = 'so-thumb'; img.src = d.img; img.alt = ''; row.querySelector('.so-thumb').replaceWith(img); }
        linesBox.appendChild(row);
        find.value = ''; close(); recalc();
        row.querySelector('[data-qty]').focus();
    }
    find.addEventListener('focus', open);
    find.addEventListener('input', open);
    find.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowDown') { e.preventDefault(); if (results.hidden) open(); else highlight(active + 1); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); highlight(active - 1); }
        else if (e.key === 'Enter') { e.preventDefault(); var list = shown(); if (!results.hidden && list[active]) add(list[active]); }
        else if (e.key === 'Escape') { close(); }
    });
    form.querySelector('[data-browse]').addEventListener('click', function () { find.value = ''; open(); find.focus(); });
    results.addEventListener('mousedown', function (e) { if (e.target.closest('a')) return; e.preventDefault(); });
    results.addEventListener('click', function (e) { var b = e.target.closest('.so-item'); if (b) add(b); });
    document.addEventListener('click', function (e) { if (!e.target.closest('[data-picker]')) close(); });
    linesBox.addEventListener('input', recalc);
    linesBox.addEventListener('click', function (e) {
        var row = e.target.closest('[data-line]'); if (!row) return;
        if (e.target.closest('[data-remove]')) { row.remove(); recalc(); return; }
        if (e.target.closest('[data-toggle]')) {
            var open = row.classList.toggle('is-open');
            row.querySelector('[data-extra]').hidden = !open;
            e.target.closest('[data-toggle]').setAttribute('aria-expanded', open ? 'true' : 'false');
        }
    });
    form.addEventListener('submit', function (e) {
        if (!linesBox.querySelector('[data-line]')) { e.preventDefault(); find.focus(); open(); alert('Add at least one product to the order.'); }
    });
    recalc();
})();
</script>
@endif
@endsection
