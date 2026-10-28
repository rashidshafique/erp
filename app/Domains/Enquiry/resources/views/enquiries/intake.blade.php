{{-- The enquiry (design 1b), one page for a new enquiry and for a saved one at any status. A bar with the code, the status and the
     actions; on the left the customer and the kind of request, then each part with its volumes and quality requirements; on the
     right the drawing, the files, similar parts we have made and what happens next. Won, lost and cancelled enquiries are read only.
     A saved enquiry also has the Quotation and Files & history tabs. --}}
@extends('layouts.app')
@section('title', $e?->code ?? 'New enquiry')
@section('breadcrumb')<a href="{{ route('enquiries.index') }}">Enquiries</a> / {{ $e?->code ?? 'New' }} @endsection
@section('content')
@php
    use App\Domains\Enquiry\Models\Enquiry;
    use App\Domains\Enquiry\Services\EnquiryIntake;
    $open = ! $e || $e->isOpen();
    $status = $e?->status ?? 'NEW';
    $statusLabel = ['NEW' => 'Draft', 'FEASIBILITY' => 'Feasibility', 'QUOTED' => 'Quoted', 'WON' => 'Won', 'LOST' => 'Lost', 'CANCELLED' => 'Cancelled'][$status];
    $statusTone = ['NEW' => 'ei-grey', 'FEASIBILITY' => 'ei-warn', 'QUOTED' => 'ei-blue', 'WON' => 'ei-ok', 'LOST' => 'ei-bad', 'CANCELLED' => 'ei-bad'][$status];
    $qcls = ['DRAFT' => 'ei-grey', 'ISSUED' => 'ei-blue', 'ACCEPTED' => 'ei-ok', 'REJECTED' => 'ei-bad', 'EXPIRED' => 'ei-bad', 'SUPERSEDED' => 'ei-grey'];
    $kind = old('source_type', $e?->source_type ?? ($preset['scenario'] ?? null));
    $customerId = old('customer_id', $e?->customer_id ?? ($preset['customer'] ?? null));
    $customer = $customerId ? $customers->firstWhere('id', $customerId) ?? $e?->customer : null;
    $notes = EnquiryIntake::NOTES;
    $scJs = collect($scenarios)->map(fn ($s) => ['short' => $s['short'], 'advice' => $s['advice'], 'cat' => $s['file_category']])->all();
    $code = $e?->code ?? app(\App\Services\Codes\CodeGenerator::class)->preview('enquiry');
    $draftQuote = $e?->quotations->firstWhere('status', 'DRAFT');
    $latestQuote = $e?->quotations->sortByDesc('version')->first();
    if (is_array(old('items'))) {
        $rows = collect(old('items'))->map(fn ($r, $k) => ['key' => $k, 'item' => ! empty($r['id']) ? $e?->items->firstWhere('id', $r['id']) : null]);
    } elseif ($e && $e->items->isNotEmpty()) {
        $rows = $e->items->sortBy('line_no')->values()->map(fn ($it, $k) => ['key' => $k, 'item' => $it]);
    } else {
        $rows = collect([['key' => 0, 'item' => null]]);
    }
    $preview = $files->first(fn ($f) => $f->isInlineViewable());
    $guide = $e ? \App\Domains\Enquiry\Services\EnquiryGuide::steps($e) : [];
@endphp
@include('partials.search-select')
<style>
    .ei-card { background: var(--surface, #fff); border: 1px solid var(--border); border-radius: 10px; }
    .ei-bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; padding: 14px 28px; border-bottom: 1px solid var(--border); }
    .ei-crumb { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--muted); flex-wrap: wrap; } .ei-crumb code { color: var(--text); } .ei-crumb a { color: var(--muted); }
    .ei-crumb strong { color: var(--text); font-weight: 500; margin-left: 4px; }
    .ei-actions { display: flex; gap: 8px; flex-wrap: wrap; }
    .ei-btn { padding: 7px 14px; border-radius: 6px; background: var(--surface, #fff); border: 1px solid var(--border); font-size: 13px; color: var(--text); cursor: pointer; text-decoration: none !important; white-space: nowrap; }
    .ei-btn.primary { background: var(--accent); border-color: var(--accent); color: #fff; font-weight: 500; }
    .ei-btn:disabled { opacity: .6; cursor: default; }
    .ei-pill { font-size: 11.5px; padding: 2px 8px; border-radius: 999px; font-weight: 500; white-space: nowrap; background: #f0f1f3; color: #55585d; }
    .ei-pill.ei-ok { background: #e7f4ec; color: #1d7048; } .ei-pill.ei-warn { background: #fbf0dc; color: #94610a; } .ei-pill.ei-bad { background: #fbe9e7; color: #b3261e; } .ei-pill.ei-blue { background: #eaf0fd; color: var(--accent); }
    .ei-grid { display: grid; grid-template-columns: minmax(0, 1fr) 400px; }
    .ei-main { padding: 24px 28px; display: flex; flex-direction: column; gap: 24px; min-width: 0; }
    .ei-side { border-left: 1px solid var(--border); padding: 24px; background: #f8f8f9; display: flex; flex-direction: column; gap: 18px; min-width: 0; }
    .ei-h { font-size: 13px; font-weight: 600; margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
    .ei-sub { font-size: 12px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .06em; margin: 16px 0 10px; }
    .ei-grid3 { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; }
    .ei-grid4 { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; }
    .ei-s2 { grid-column: span 2; } .ei-s3 { grid-column: span 3; } .ei-s4, .ei-full { grid-column: 1 / -1; }
    .ei-f { display: flex; flex-direction: column; gap: 6px; font-size: 12px; color: var(--muted); min-width: 0; }
    .ei-f input:not([type=checkbox]):not([type=radio]), .ei-f select, .ei-f textarea { font-size: 14px; color: var(--text); width: 100%; }
    .ei-f .error-text { font-size: 12px; }
    .ei-help { font-size: 11.5px; color: var(--muted); }
    .ei-checks { flex-direction: row; align-items: flex-end; gap: 14px; flex-wrap: wrap; } .ei-checks label { display: inline-flex; gap: 6px; align-items: center; color: var(--text); font-size: 13px; }
    .ei-custrow { display: flex; align-items: center; gap: 10px; } .ei-custrow select { flex: 1 1 auto; min-width: 0; }
    .ei-opt { display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; color: var(--text); font-size: 13px; }
    .ei-badge { font-size: 11px; padding: 1px 6px; border-radius: 4px; background: #eaf0fd; color: var(--accent); white-space: nowrap; }
    .ei-chips { display: flex; flex-wrap: wrap; gap: 8px; font-size: 13px; }
    .ei-chip { display: inline-flex; align-items: center; gap: 4px; padding: 6px 12px; border-radius: 999px; border: 1px solid var(--border); background: var(--surface, #fff); color: var(--text); cursor: pointer; position: relative; }
    .ei-chip.on { background: var(--accent); border-color: var(--accent); color: #fff; }
    .ei-chip input { position: absolute; opacity: 0; pointer-events: none; }
    .ei-chip:has(input:focus-visible) { outline: 2px solid var(--accent); outline-offset: 1px; }
    .ei-part { border-top: 1px solid var(--border); padding-top: 20px; } .ei-part:first-child { border-top: 0; padding-top: 0; }
    .ei-part-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; }
    .ei-part-head .ei-h { margin-bottom: 12px; }
    .ei-remove { font-size: 12.5px; color: var(--muted); display: inline-flex; gap: 6px; align-items: center; }
    .ei-link { border: 0; background: none; color: var(--accent); font-size: 13px; cursor: pointer; padding: 0; }
    .ei-cost { margin-top: 14px; font-size: 13px; } .ei-cost summary { cursor: pointer; color: var(--muted); margin-bottom: 10px; }
    .ei-strip { margin-top: 14px; padding: 10px 12px; border-radius: 8px; background: #f8f8f9; border: 1px solid var(--border); display: flex; flex-direction: column; gap: 6px; font-size: 13px; }
    .ei-strip-forms { display: flex; flex-direction: column; gap: 6px; } .ei-inline { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; } .ei-inline select, .ei-inline input { max-width: 320px; }
    .ei-lab { font-size: 12px; color: var(--muted); margin-right: 4px; } .ei-muted { color: var(--muted); }
    .ei-preview { height: 220px; border-radius: 8px; border: 1px solid #e0e1e4; background: repeating-linear-gradient(135deg, #fff 0 8px, #f1f2f4 8px 16px); display: flex; align-items: center; justify-content: center; overflow: hidden; }
    .ei-preview img { max-width: 100%; max-height: 100%; object-fit: contain; } .ei-preview iframe { width: 100%; height: 100%; border: 0; background: #fff; }
    .ei-preview code { font-size: 11.5px; color: var(--muted); background: #fff; padding: 4px 8px; border-radius: 4px; }
    .ei-files { display: flex; gap: 8px; flex-wrap: wrap; font-size: 12px; } .ei-files a { padding: 4px 8px; background: #fff; border: 1px solid var(--border); border-radius: 5px; color: var(--muted); text-decoration: none; } .ei-files a:hover { border-color: var(--accent); }
    .ei-side-h { font-size: 12px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .06em; margin-bottom: 8px; }
    .ei-list { display: flex; flex-direction: column; background: #fff; border: 1px solid var(--border); border-radius: 8px; }
    .ei-list > div { display: flex; justify-content: space-between; gap: 10px; padding: 10px 12px; border-bottom: 1px solid #eff0f2; font-size: 13px; } .ei-list > div:last-child { border-bottom: 0; }
    .ei-list small { display: block; font-size: 12px; color: var(--muted); } .ei-list code { font-size: 12px; color: var(--muted); }
    .ei-guide { list-style: none; margin: 0; padding: 0; font-size: 13px; } .ei-guide li { padding: 3px 0; }
    .ei-upload { display: flex; flex-direction: column; gap: 6px; font-size: 12px; color: var(--muted); }
    .ei-errors { margin: 16px 28px 0; }
    .ei-card [hidden] { display: none !important; }
    .ei-card fieldset { border: 0; margin: 0; padding: 0; min-width: 0; }
    .ei-star::after { content: '*'; color: #c0392b; font-weight: 600; }
    .ei-card input[type=checkbox], .ei-card input[type=radio] { accent-color: var(--accent); }
    @media (max-width: 1100px) { .ei-grid { grid-template-columns: 1fr; } .ei-side { border-left: 0; border-top: 1px solid var(--border); } }
    @media (max-width: 700px) { .ei-grid3, .ei-grid4 { grid-template-columns: 1fr; } .ei-s2, .ei-s3 { grid-column: 1 / -1; } .ei-bar, .ei-main { padding-left: 16px; padding-right: 16px; } .ei-custrow { flex-wrap: wrap; } }
</style>

@if ($e && $status === 'NEW')<div class="tab-marker" data-tab-start="Enquiry"></div>@endif
@if (session('error'))<div class="alert alert-error">{{ session('error') }}</div>@endif
<form method="POST" action="{{ $e ? route('enquiries.update', $e) : route('enquiries.store') }}" enctype="multipart/form-data" id="ei" novalidate data-no-save-bar>@csrf @if ($e) @method('PUT') @endif
<div class="ei-card">
    <div class="ei-bar">
        <div class="ei-crumb"><a href="{{ route('enquiries.index') }}">Enquiries</a><span>/</span><code>{{ $code }}</code><span class="ei-pill {{ $statusTone }}">{{ $statusLabel }}</span>@if ($e)<strong>{{ $e->title }}</strong>@endif</div>
        <div class="ei-actions">
            @if (! $e)
                <a class="ei-btn" href="{{ route('enquiries.index') }}">Cancel</a>
                <button class="ei-btn" type="submit" name="action" value="draft">Save draft</button>
                <button class="ei-btn primary" type="submit" name="action" value="send">Send to feasibility</button>
            @elseif ($status === 'NEW')
                <button class="ei-btn" type="button" data-quick="closeDialog">Close enquiry</button>
                @if (Route::has('developments.create'))<a class="ei-btn" href="{{ route('developments.create', ['from' => 'enquiry:'.$e->id]) }}">Start pattern development</a>@endif
                <button class="ei-btn" type="submit" name="action" value="draft">Save draft</button>
                <button class="ei-btn primary" type="submit" name="action" value="send">Send to feasibility</button>
            @elseif ($open)
                <a class="ei-btn" href="{{ route('enquiries.show', $e) }}">Back to the record</a>
                <button class="ei-btn primary" type="submit" name="action" value="save">Save</button>
            @else
                <a class="ei-btn" href="{{ route('enquiries.index') }}">All enquiries</a>
            @endif
        </div>
    </div>
    @if ($errors->any())<div class="alert alert-error ei-errors" role="alert">@foreach (collect($errors->all())->unique() as $message)<div>{{ $message }}</div>@endforeach</div>@endif

    <fieldset @disabled(! $open)>
    <div class="ei-grid">
        <div class="ei-main">
            <div>
                <div class="ei-h">Customer</div>
                <div class="ei-grid3">
                    <div class="ei-f ei-full"><span>Customer <span class="ei-star"></span></span>
                        <div class="ei-custrow">
                            <label class="ei-opt"><input type="radio" name="customer_mode" value="EXISTING" @checked(old('customer_mode', 'EXISTING') === 'EXISTING') aria-label="Existing customer"></label>
                            <select id="customer_id" name="customer_id" data-search-select aria-label="Existing customer"><option value="">— choose existing customer —</option>@foreach ($customers as $c)<option value="{{ $c->id }}" data-sub="{{ $c->code }}" data-contact="{{ $c->contact_name }}" data-ppap="{{ $c->ppap_requested ? $c->ppap_level : '' }}" @selected($customerId === $c->id)>{{ $c->name }}</option>@endforeach
                                @if ($e && $e->customer && ! $customers->contains('id', $e->customer_id))<option value="{{ $e->customer_id }}" selected>{{ $e->customer->name }} (inactive)</option>@endif</select>
                            <span class="ei-badge" id="ppapBadge" @unless ($customer?->ppap_requested) hidden @endunless>PPAP L{{ $customer?->ppap_level }}</span>
                            <label class="ei-opt"><input type="radio" name="customer_mode" value="NEW" @checked(old('customer_mode') === 'NEW')> New customer</label>
                        </div>
                        @error('customer_id')<span class="error-text">{{ $message }}</span>@enderror
                    </div>
                    <label class="ei-f" data-mode="NEW">Company name <span class="ei-star"></span><input name="new_name" value="{{ old('new_name') }}">@error('new_name')<span class="error-text">{{ $message }}</span>@enderror<span class="ei-help">A short customer code is made for you.</span></label>
                    <label class="ei-f" data-mode="NEW">Contact person<input name="new_contact" value="{{ old('new_contact') }}"></label>
                    <label class="ei-f" data-mode="NEW">Phone<input name="new_phone" value="{{ old('new_phone') }}"></label>
                    <label class="ei-f" data-mode="NEW">Email<input type="email" name="new_email" value="{{ old('new_email') }}">@error('new_email')<span class="error-text">{{ $message }}</span>@enderror</label>
                    <label class="ei-f" data-mode="NEW">Payment terms<input name="new_terms" value="{{ old('new_terms') }}" placeholder="e.g. 30 days"></label>
                    <label class="ei-f">Customer RFQ ref<input name="customer_reference" maxlength="100" value="{{ old('customer_reference', $e?->customer_reference) }}"></label>
                    <label class="ei-f">Reply by<input type="date" name="due_on" value="{{ old('due_on', $e?->due_on?->toDateString()) }}"></label>
                    <label class="ei-f">Contact for this request<input name="contact_name" maxlength="120" value="{{ old('contact_name', $e?->contact_name) }}" placeholder="Defaults to the customer's contact"></label>
                    <label class="ei-f">Owner<select name="owner_id"><option value="">— me —</option>@foreach ($users as $u)<option value="{{ $u->id }}" @selected((string) old('owner_id', $e?->owner_id ?? auth()->id()) === (string) $u->id)>{{ $u->name }}</option>@endforeach</select></label>
                    <label class="ei-f ei-s2">Enquiry title<input name="title" maxlength="200" value="{{ old('title', $e?->title) }}" placeholder="Left blank, it is made from the first part and the kind of request"></label>
                </div>
            </div>

            <div>
                <div class="ei-h">Kind of request <span class="ei-star"></span></div>
                <div class="ei-chips" id="kindChips">
                    @foreach (Enquiry::SOURCES as $k => $l)
                        <label class="ei-chip @if ($kind === $k) on @endif"><input type="radio" name="source_type" value="{{ $k }}" @checked($kind === $k)>{{ $scenarios[$k]['label'] ?? $l }}</label>
                    @endforeach
                </div>
                @error('source_type')<span class="error-text">{{ $message }}</span>@enderror
                <label class="ei-f" style="margin-top:12px"><span id="noteLabel">{{ $notes[$kind] ?? 'What they said' }}</span><textarea name="summary" rows="3" id="noteBox" placeholder="Anything the customer said that the team should know">{{ old('summary', $e?->summary) }}</textarea>@error('summary')<span class="error-text">{{ $message }}</span>@enderror</label>
            </div>

            <div>
                <div id="parts">
                    @foreach ($rows as $row)
                        @include('enquiry::enquiries._intake_part', ['key' => $row['key'], 'it' => $row['item'], 'num' => $loop->iteration])
                    @endforeach
                </div>
                @error('items')<span class="error-text">{{ $message }}</span>@enderror
                @if ($open)<button type="button" class="ei-btn" id="addPart" style="margin-top:14px">+ Add part</button>@endif
            </div>
        </div>

        <aside class="ei-side">
            <div class="ei-preview">
                @if ($preview && $preview->isImage())<img src="{{ route('attachments.download', $preview) }}" alt="{{ $preview->original_name }}">
                @elseif ($preview)<iframe src="{{ route('attachments.download', $preview) }}" title="{{ $preview->original_name }}"></iframe>
                @else<code>{{ $files->isEmpty() ? 'No drawing yet' : 'No preview for these files' }}</code>@endif
            </div>
            @if ($files->isNotEmpty())<div class="ei-files">@foreach ($files as $f)<a href="{{ route('attachments.download', $f) }}" target="_blank" rel="noopener"><code>{{ $f->original_name }}</code></a>@endforeach</div>@endif
            @if ($open)
                <div class="ei-upload">
                    <label class="ei-f">Add drawing, model, photos or the customer's email<input type="file" name="files[]" multiple data-max-kb="{{ \App\Support\Uploads::effectiveMaxKb() }}"></label>
                    @error('files')<span class="error-text">{{ $message }}</span>@enderror @error('files.*')<span class="error-text">{{ $message }}</span>@enderror
                    <label class="ei-f">Kind of file<select name="file_category" id="file_category">@foreach ($categories as $k => $l)<option value="{{ $k }}" @selected(old('file_category', $scenarios[$kind]['file_category'] ?? null) === $k)>{{ $l }}</option>@endforeach</select></label>
                </div>
            @endif

            <div>
                <div class="ei-side-h">Similar parts we've made</div>
                @if ($similar->isEmpty())
                    <div class="ei-muted" style="font-size:12.5px">{{ $e ? 'None found with this alloy and weight.' : 'Shown once the part has an alloy and is saved.' }}</div>
                @else
                    <div class="ei-list">
                        @foreach ($similar as $s)<div><div><a href="{{ route('products.show', $s['product']) }}" style="font-weight:500;color:inherit">{{ $s['product']->name }}, {{ $s['product']->code }}</a><small>{{ $s['grade'] }}{{ $s['weight'] ? ' · '.\App\Support\Formats::number($s['weight'], 2).' kg' : '' }}</small></div>@if ($s['pattern'])<code>{{ $s['pattern'] }}</code>@endif</div>@endforeach
                    </div>
                @endif
            </div>

            <div>
                <div class="ei-side-h">What happens next</div>
                @if ($e)
                    <ul class="ei-guide">@foreach ($guide as $g)<li>@if ($g['done'] === true)<span style="color:var(--accent)">&#10003;</span> {{ $g['label'] }}@elseif ($g['done'] === false)<span class="ei-muted">&#9675;</span> {{ $g['label'] }}@else<span class="ei-muted">&rarr; {{ $g['label'] }}</span>@endif</li>@endforeach</ul>
                @else
                    <ul class="ei-guide" id="adviceList"></ul>
                @endif
            </div>

            @if ($latestQuote)
                <div>
                    <div class="ei-side-h">Quotation</div>
                    <div class="ei-list"><div><div><a href="{{ route('quotations.show', $latestQuote) }}" style="color:inherit;font-weight:500">{{ $latestQuote->code }}</a><small>Version {{ $latestQuote->version }}{{ $latestQuote->lines->isEmpty() ? '' : ' · lowest margin '.\App\Support\Formats::number($latestQuote->lines->min('effective_margin_pct'), 1).'%' }}</small></div><span class="ei-pill {{ $qcls[$latestQuote->status] ?? '' }}">{{ ucfirst(strtolower($latestQuote->status)) }}</span></div></div>
                </div>
            @endif
        </aside>
    </div>
    </fieldset>
</div>
</form>

<template id="partTemplate">@include('enquiry::enquiries._intake_part', ['key' => '__I__', 'it' => null, 'num' => '__N__'])</template>

@if ($e && $open)
    @foreach ($e->items as $it)
        <form id="ana-{{ $it->id }}" method="POST" action="{{ route('enquiries.items.analysis', [$e, $it]) }}">@csrf</form>
        <form id="fea-{{ $it->id }}" method="POST" action="{{ route('enquiries.items.feasibility', [$e, $it]) }}">@csrf</form>
    @endforeach
    @include('enquiry::enquiries._close_dialog')
@endif

@if ($e && $status === 'NEW')
    <div class="tab-marker" data-tab-start="Files & history"></div>
    @include('attachments._panel', ['type' => 'enquiry', 'id' => $e->id])
    @include('audit._history', ['type' => 'Enquiry', 'id' => $e->id])
@endif

<script>
(function () {
    var NOTES = {!! json_encode($notes, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};
    var SC = {!! json_encode($scJs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};
    var OWN = {!! json_encode(EnquiryIntake::OWN_PART) !!};
    var form = document.getElementById('ei'), parts = document.getElementById('parts');
    if (!form) return;
    var next = {{ $rows->keys()->map(fn ($k) => (int) $rows[$k]['key'])->max() + 1 }};
    function checked(n) { var e = form.querySelector('[name="' + n + '"]:checked'); return e ? e.value : ''; }
    function kind() { return checked('source_type'); }
    function toggle(g, on) { g.hidden = !on; g.querySelectorAll('input,select,textarea').forEach(function (i) { i.disabled = !on || form.querySelector('fieldset').disabled; }); }

    function applyMode() { form.querySelectorAll('[data-mode]').forEach(function (g) { toggle(g, g.getAttribute('data-mode') === (checked('customer_mode') || 'EXISTING')); }); }
    function applyKind() {
        var k = kind();
        form.querySelectorAll('#kindChips .ei-chip').forEach(function (c) { c.classList.toggle('on', c.querySelector('input').checked); });
        var label = document.getElementById('noteLabel');
        label.textContent = NOTES[k] || 'What they said';
        parts.querySelectorAll('[data-own]').forEach(function (g) { toggle(g, OWN.indexOf(k) >= 0); });
        var list = document.getElementById('adviceList');
        if (list) { list.innerHTML = ''; ((SC[k] || {}).advice || ['Choose the kind of request to see what usually follows.']).forEach(function (a) { var li = document.createElement('li'); li.textContent = '→ ' + a; list.appendChild(li); }); }
        var cat = document.getElementById('file_category');
        if (cat && SC[k] && !form.dataset.catTouched) cat.value = SC[k].cat;
    }
    function applyPart(p) {
        p.querySelectorAll('[data-quality] .ei-chip').forEach(function (c) { c.classList.toggle('on', c.querySelector('input').checked); });
        var ppap = p.querySelector('[data-ppap]'), lvl = p.querySelector('[data-ppap-level]');
        if (ppap && lvl) toggle(lvl, ppap.checked);
        var sel = p.querySelector('[data-material]'), box = p.querySelector('[data-material-new]');
        if (sel && box) toggle(box, sel.value === '__new__');
        var lab = p.querySelector('[data-ppap-label]'), lv = p.querySelector('[name$="[ppap_level]"]');
        if (lab) lab.textContent = ppap && ppap.checked && lv && lv.value ? ' Level ' + lv.value : '';
    }
    function renumber() { parts.querySelectorAll('[data-part]').forEach(function (p, i) { var n = p.querySelector('[data-num]'); if (n) n.textContent = i + 1; }); }
    function customerPpap() { var s = document.getElementById('customer_id'), o = s && s.selectedIndex > 0 ? s.options[s.selectedIndex] : null; return o && checked('customer_mode') !== 'NEW' ? o.dataset.ppap : ''; }
    function presetPpap(p) {
        var level = customerPpap(), box = p.querySelector('[data-ppap]'), sel = p.querySelector('[name$="[ppap_level]"]');
        if (!level || !box || p.dataset.ppapTouched) return;
        box.checked = true; if (sel) sel.value = level;
    }
    function applyCustomer() {
        var level = customerPpap(), badge = document.getElementById('ppapBadge');
        if (badge) { badge.hidden = !level; badge.textContent = 'PPAP L' + level; }
        parts.querySelectorAll('[data-part][data-new]').forEach(function (p) { presetPpap(p); applyPart(p); });
    }

    form.addEventListener('change', function (ev) {
        var t = ev.target, p = t.closest && t.closest('[data-part]');
        if (t.name === 'customer_id' && t.value) { form.querySelector('[name=customer_mode][value=EXISTING]').checked = true; }
        if (t.name === 'customer_mode' || t.name === 'customer_id') { applyMode(); applyCustomer(); }
        if (t.name === 'source_type') applyKind();
        if (t.id === 'file_category') form.dataset.catTouched = '1';
        if (p) {
            if (t.matches('[data-ppap], [name$="[ppap_level]"]')) p.dataset.ppapTouched = '1';
            if (t.matches('[data-material]')) {
                var o = t.options[t.selectedIndex], fam = p.querySelector('[data-family]');
                if (o && o.dataset.family && fam && (o.dataset.family === 'GREY' || o.dataset.family === 'DUCTILE')) fam.value = o.dataset.family;
            }
            if (t.matches('[data-product]')) {
                var po = t.selectedIndex > 0 ? t.options[t.selectedIndex] : null, info = p.querySelector('[data-prod-info]'), desc = p.querySelector('[name$="[description]"]');
                if (info) info.textContent = po ? 'Revisions: ' + po.dataset.revs + (po.dataset.weight ? ' · nominal weight ' + po.dataset.weight + ' kg' : '') : '';
                if (po && desc && !desc.value.trim()) desc.value = po.dataset.name;
            }
            applyPart(p);
        }
    });
    var add = document.getElementById('addPart');
    if (add) add.addEventListener('click', function () {
        var html = document.getElementById('partTemplate').innerHTML.replace(/__I__/g, String(next++)).replace(/__N__/g, '');
        var wrap = document.createElement('div'); wrap.innerHTML = html.trim();
        var p = wrap.firstElementChild; parts.appendChild(p);
        renumber(); applyKind(); presetPpap(p); applyPart(p);
        var first = p.querySelector('[name$="[description]"]'); if (first) first.focus();
    });
    parts.addEventListener('click', function (ev) {
        var b = ev.target.closest('[data-remove-part]');
        if (!b) return;
        if (parts.querySelectorAll('[data-part]').length <= 1) { alert('An enquiry needs at least one part. Change this one instead.'); return; }
        b.closest('[data-part]').remove(); renumber();
    });
    form.addEventListener('submit', function (ev) {
        if (ev.submitter && ev.submitter.name && ev.submitter.getAttribute('form') === null) {
            var h = document.createElement('input'); h.type = 'hidden'; h.name = ev.submitter.name; h.value = ev.submitter.value; form.appendChild(h);
        }
        form.querySelectorAll('.ei-actions button[type=submit]').forEach(function (b) { b.disabled = true; });
    });
    applyMode(); applyKind(); applyCustomer();
    parts.querySelectorAll('[data-part]').forEach(applyPart);
})();
</script>
@endsection
