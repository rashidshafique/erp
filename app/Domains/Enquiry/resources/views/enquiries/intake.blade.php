{{-- The enquiry (design 1b), one page for a new enquiry and for a saved one at any status. A bar with the code, the status and the
     actions; on the left the customer, then each part with its volumes and quality requirements; on the right the drawing, the files and
     similar parts we have made. Fields 1b does not show sit in a hidden block (data-kept) so they are still saved, and the kind of request
     is kept there too, defaulting to a drawing; its note box and the own-part picker appear only when the kind needs them. Won, lost and
     cancelled enquiries are read only. A saved enquiry also has the Quotation and Files & history tabs. --}}
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
    $kind = old('source_type', $e?->source_type ?? ($preset['scenario'] ?? 'DRAWING'));
    $customerId = old('customer_id', $e?->customer_id ?? ($preset['customer'] ?? null));
    $notes = EnquiryIntake::NOTES;
    $scJs = collect($scenarios)->map(fn ($s, $k) => ['short' => $s['short'], 'advice' => $s['advice'], 'cat' => $s['file_category'], 'drawing' => $k !== 'CHANGE' && in_array('drawing', $s['needs'] ?? [], true)])->all();
    $code = $e?->code ?? app(\App\Services\Codes\CodeGenerator::class)->preview('enquiry');
    $latestQuote = $e?->quotations->sortByDesc('version')->first();
    if (is_array(old('items'))) {
        $rows = collect(old('items'))->map(fn ($r, $k) => ['key' => $k, 'item' => ! empty($r['id']) ? $e?->items->firstWhere('id', $r['id']) : null]);
    } elseif ($e && $e->items->isNotEmpty()) {
        $rows = $e->items->sortBy('line_no')->values()->map(fn ($it, $k) => ['key' => $k, 'item' => $it]);
    } else {
        $rows = collect([['key' => 0, 'item' => null]]);
    }
    $multi = $rows->count() > 1;
    $preview = $files->first(fn ($f) => $f->isInlineViewable());
    $guide = $e ? \App\Domains\Enquiry\Services\EnquiryGuide::steps($e) : [];
@endphp
@include('partials.search-select')
<style>
    .ei-card { background: var(--surface, #fff); border: 1px solid var(--border); border-radius: 10px; }
    .ei-bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; padding: 14px 28px; border-bottom: 1px solid var(--border); }
    .ei-crumb { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--muted); flex-wrap: wrap; } .ei-crumb code { color: var(--text); } .ei-crumb a { color: var(--muted); }
    .ei-actions { display: flex; gap: 8px; flex-wrap: wrap; }
    .ei-btn { padding: 7px 14px; border-radius: 6px; background: var(--surface, #fff); border: 1px solid var(--border); font-size: 13px; color: var(--text); cursor: pointer; text-decoration: none !important; white-space: nowrap; }
    .ei-btn.primary { background: var(--accent); border-color: var(--accent); color: #fff; font-weight: 500; }
    .ei-btn:disabled { opacity: .6; cursor: default; }
    .ei-pill { font-size: 11.5px; padding: 2px 8px; border-radius: 999px; font-weight: 500; white-space: nowrap; background: #f0f1f3; color: #55585d; }
    .ei-pill.ei-ok { background: #e7f4ec; color: #1d7048; } .ei-pill.ei-warn { background: #fbf0dc; color: #94610a; } .ei-pill.ei-bad { background: #fbe9e7; color: #b3261e; } .ei-pill.ei-blue { background: #eaf0fd; color: var(--accent); }
    .ei-grid { display: grid; grid-template-columns: minmax(0, 1fr) 400px; }
    .ei-main { padding: 24px 28px; display: flex; flex-direction: column; gap: 24px; min-width: 0; }
    .ei-side { border-left: 1px solid var(--border); padding: 24px; background: #f8f8f9; display: flex; flex-direction: column; gap: 18px; min-width: 0; }
    .ei-h { font-size: 14px; font-weight: 600; margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
    .ei-sec { font-size: 14px; font-weight: 600; margin: 22px 0 12px; }
    .ei-grid3 { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; }
    .ei-grid4 { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; }
    .ei-s2 { grid-column: span 2; } .ei-s3 { grid-column: span 3; } .ei-s4, .ei-full { grid-column: 1 / -1; }
    .ei-f { display: flex; flex-direction: column; gap: 6px; font-size: 12px; color: var(--muted); min-width: 0; }
    .ei-f input:not([type=checkbox]):not([type=radio]), .ei-f select, .ei-f textarea { font-size: 14px; color: var(--text); width: 100%; }
    .ei-lbl { display: flex; justify-content: space-between; align-items: baseline; gap: 8px; } .ei-lbl a { font-size: 12px; }
    .ei-f .error-text { font-size: 12px; }
    .ei-help { font-size: 11.5px; color: var(--muted); }
    .ei-checks { flex-direction: row; align-items: flex-end; gap: 14px; flex-wrap: wrap; } .ei-checks label { display: inline-flex; gap: 6px; align-items: center; color: var(--text); font-size: 13px; }
    .ei-opt { display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; color: var(--text); font-size: 13px; }
    .ei-chips { display: flex; flex-wrap: wrap; gap: 8px; font-size: 13px; }
    .ei-chip { display: inline-flex; align-items: center; gap: 4px; padding: 6px 12px; border-radius: 999px; border: 1px solid var(--border); background: var(--surface, #fff); color: var(--text); cursor: pointer; position: relative; }
    .ei-chip.on { background: var(--accent); border-color: var(--accent); color: #fff; }
    .ei-chip input { position: absolute; opacity: 0; pointer-events: none; }
    .ei-chip:has(input:focus-visible) { outline: 2px solid var(--accent); outline-offset: 1px; }
    .ei-chip select { font-size: 13px; width: auto; padding: 0 2px; border: 0; background: transparent; color: inherit; }
    .ei-chip.on select option { color: var(--text); }
    .ei-chip-other { border-style: dashed; color: var(--muted); }
    .ei-chip-other input { position: static; opacity: 1; pointer-events: auto; width: 170px; border: 0; padding: 0; background: transparent; font: inherit; color: var(--text); outline: 0; }
    .ei-part { border-top: 1px solid var(--border); padding-top: 20px; } .ei-part:first-child { border-top: 0; padding-top: 0; }
    .ei-part-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; }
    .ei-part-head .ei-h { margin-bottom: 0; }
    .ei-parts-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 12px; } .ei-parts-head .ei-h { margin-bottom: 0; }
    .ei-part { display: grid; grid-template-columns: 32px minmax(0, 1fr); }
    .ei-part-no { padding-top: 28px; font-size: 15px; display: flex; flex-direction: column; align-items: flex-start; gap: 6px; } .ei-part-no .ei-link, .ei-part-no .ei-remove { font-size: 12px; }
    .ei-part-body { min-width: 0; }
    .ei-part { padding-bottom: 28px; } .ei-part:first-child .ei-part-no .ei-remove, .ei-part:first-child .ei-part-no .ei-link { display: none; }
    .ei-card .ei-f input:not([type=checkbox]):not([type=radio]), .ei-card .ei-f select { height: 40px; min-height: 40px; box-sizing: border-box; }
    .ei-chip { box-sizing: border-box; height: 36px; padding-top: 0; padding-bottom: 0; line-height: 1; }
    .ei-chip select { height: 28px; min-height: 0; padding: 0 2px; }
    .ei-chip-other input { height: 28px; min-height: 0; padding: 0; }
    .ei-remove { font-size: 12.5px; color: var(--muted); display: inline-flex; gap: 6px; align-items: center; }
    .ei-link { border: 0; background: none; color: var(--accent); font-size: 13px; cursor: pointer; padding: 0; }
    .ei-strip { margin-top: 14px; padding: 10px 12px; border-radius: 8px; background: #f8f8f9; border: 1px solid var(--border); display: flex; flex-direction: column; gap: 6px; font-size: 13px; }
    .ei-strip-forms { display: flex; flex-direction: column; gap: 6px; } .ei-inline { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; } .ei-inline select, .ei-inline input { max-width: 320px; }
    .ei-lab { font-size: 12px; color: var(--muted); margin-right: 4px; } .ei-muted { color: var(--muted); }
    .ei-preview { height: 220px; border-radius: 8px; border: 1px solid #e0e1e4; background: repeating-linear-gradient(135deg, #fff 0 8px, #f1f2f4 8px 16px); display: flex; align-items: center; justify-content: center; overflow: hidden; }
    .ei-preview img { max-width: 100%; max-height: 100%; object-fit: contain; } .ei-preview iframe { width: 100%; height: 100%; border: 0; background: #fff; }
    .ei-preview code { font-size: 11.5px; color: var(--muted); background: #fff; padding: 4px 8px; border-radius: 4px; }
    .ei-files { display: flex; gap: 8px; flex-wrap: wrap; font-size: 12px; }
    .ei-file { display: inline-flex; align-items: center; gap: 6px; max-width: 100%; padding: 5px 9px; background: #fff; border: 1px solid var(--border); border-radius: 6px; color: var(--muted); cursor: pointer; font-size: 12px; }
    .ei-file:hover { border-color: var(--accent); } .ei-file.on { border-color: var(--accent); color: var(--text); box-shadow: 0 0 0 1px var(--accent); }
    .ei-file-ico { font-size: 14px; } .ei-file-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 140px; }
    .ei-file-x { border: 0; background: none; color: var(--muted); cursor: pointer; padding: 0 0 0 2px; font-size: 14px; line-height: 1; }
    .ei-file-new { border-style: dashed; }
    .ei-side-h { font-size: 12px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .06em; margin-bottom: 8px; }
    .ei-list { display: flex; flex-direction: column; background: #fff; border: 1px solid var(--border); border-radius: 8px; }
    .ei-list > div { display: flex; justify-content: space-between; gap: 10px; padding: 10px 12px; border-bottom: 1px solid #eff0f2; font-size: 13px; } .ei-list > div:last-child { border-bottom: 0; }
    .ei-list small { display: block; font-size: 12px; color: var(--muted); } .ei-list code { font-size: 12px; color: var(--muted); }
    .ei-guide { list-style: none; margin: 0; padding: 0; font-size: 13px; } .ei-guide li { padding: 3px 0; }
    .ei-upload { display: flex; flex-direction: column; gap: 6px; font-size: 12px; color: var(--muted); }
    .ei-errors { margin: 16px 28px 0; }
    .ei-card [hidden] { display: none !important; }
    .ei-card fieldset { border: 0; margin: 0; padding: 0; min-width: 0; }
    .ei-card [aria-invalid=true] { border-color: var(--danger); }
    .ei-f:has(> .ei-star) { flex-direction: row; flex-wrap: wrap; align-items: baseline; gap: 6px 2px; } .ei-f:has(> .ei-star) > :not(.ei-star) { flex: 0 0 100%; }
    .ei-star::after { content: '*'; color: #c0392b; font-weight: 600; }
    .ei-card input[type=checkbox], .ei-card input[type=radio] { accent-color: var(--accent); }
    @media (max-width: 1100px) { .ei-grid { grid-template-columns: 1fr; } .ei-side { border-left: 0; border-top: 1px solid var(--border); } }
    @media (max-width: 700px) { .ei-grid3, .ei-grid4 { grid-template-columns: 1fr; } .ei-s2, .ei-s3 { grid-column: 1 / -1; } .ei-bar, .ei-main { padding-left: 16px; padding-right: 16px; } }
</style>

@if ($e && $status === 'NEW')<div class="tab-marker" data-tab-start="Enquiry"></div>@endif
@if (session('error'))<div class="alert alert-error">{{ session('error') }}</div>@endif
<form method="POST" action="{{ $e ? route('enquiries.update', $e) : route('enquiries.store') }}" enctype="multipart/form-data" id="ei" novalidate data-no-save-bar>@csrf @if ($e) @method('PUT') @endif
<div class="ei-card">
    <div class="ei-bar">
        <div class="ei-crumb"><a href="{{ route('enquiries.index') }}">Enquiries</a><span>/</span><code>{{ $code }}</code><span class="ei-pill {{ $statusTone }}">{{ $statusLabel }}</span></div>
        <div class="ei-actions">
            @if (! $e)
                <button class="ei-btn" type="submit" name="action" value="draft">Save draft</button>
                <button class="ei-btn primary" type="submit" name="action" value="send">Send to feasibility</button>
            @elseif ($status === 'NEW')
                <button class="ei-btn" type="button" data-quick="closeDialog">Close enquiry</button>
                <button class="ei-btn" type="submit" name="action" value="draft">Save draft</button>
                @if (Route::has('developments.create'))<a class="ei-btn" href="{{ route('developments.create', ['from' => 'enquiry:'.$e->id]) }}">Start pattern development</a>@endif
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
    @unless ($e)@include('partials.quick-dialog', ['id' => 'qd-prospect', 'title' => 'Add a prospect', 'action' => route('customers.store'), 'submit' => 'Add prospect', 'fields' => 'customers._quick-prospect-fields'])@endunless
    <div class="ei-grid">
        <div class="ei-main">
            <div>
                <div class="ei-h">Customer</div>
                <div class="ei-grid3">
                    <label class="ei-f"><span class="ei-lbl"><span>Customer <span class="ei-star"></span></span>@if (! $e)<a href="{{ route('customers.create') }}" class="qd-add" data-quick="qd-prospect" data-quick-target="customer_id">Add prospect</a>@endif</span>
                        <select id="customer_id" name="customer_id" data-search-select aria-label="Customer"><option value="">— choose customer —</option>@foreach ($customers as $c)<option value="{{ $c->id }}" data-sub="{{ $c->code }}" data-contact="{{ $c->contact_name }}" data-ppap="{{ $c->ppap_requested ? $c->ppap_level : '' }}" @selected($customerId === $c->id)>{{ $c->name }}@if ($c->is_prospect) · prospect @endif</option>@endforeach
                            @if ($e && $e->customer && ! $customers->contains('id', $e->customer_id))<option value="{{ $e->customer_id }}" selected>{{ $e->customer->name }} (inactive)</option>@endif</select>
                        @error('customer_id')<span class="error-text">{{ $message }}</span>@enderror
                    </label>
                    <label class="ei-f">Customer RFQ ref<input name="customer_reference" maxlength="100" value="{{ old('customer_reference', $e?->customer_reference) }}"></label>
                    <label class="ei-f">Reply by<input type="date" name="due_on" value="{{ old('due_on', $e?->due_on?->toDateString()) }}"></label>
                </div>
                <div data-kept hidden>
                    <input type="hidden" name="customer_mode" value="EXISTING">
                    <label class="ei-f">Contact for this request<input name="contact_name" maxlength="120" value="{{ old('contact_name', $e?->contact_name) }}"></label>
                    <label class="ei-f">Owner<select name="owner_id"><option value="">— me —</option>@foreach ($users as $u)<option value="{{ $u->id }}" @selected((string) old('owner_id', $e?->owner_id ?? auth()->id()) === (string) $u->id)>{{ $u->name }}</option>@endforeach</select></label>
                    <label class="ei-f">Enquiry title<input name="title" maxlength="200" value="{{ old('title', $e?->title) }}"></label>
                </div>
            </div>

            <div data-kept hidden>
                <div class="ei-chips" id="kindChips">
                    @foreach (Enquiry::SOURCES as $k => $l)
                        <label class="ei-chip @if ($kind === $k) on @endif"><input type="radio" name="source_type" value="{{ $k }}" @checked($kind === $k)>{{ $scenarios[$k]['label'] ?? $l }}</label>
                    @endforeach
                </div>
                @error('source_type')<span class="error-text">{{ $message }}</span>@enderror
                <label class="ei-f">Kind of file<select name="file_category" id="file_category">@foreach ($categories as $k => $l)<option value="{{ $k }}" @selected(old('file_category', $scenarios[$kind]['file_category'] ?? null) === $k)>{{ $l }}</option>@endforeach</select></label>
            </div>

            <div id="noteBlock" @unless (isset($notes[$kind])) hidden @endunless>
                <label class="ei-f"><span id="noteLabel">{{ $notes[$kind] ?? '' }}</span><textarea name="summary" rows="3" id="noteBox" placeholder="Anything the customer said that the team should know">{{ old('summary', $e?->summary) }}</textarea>@error('summary')<span class="error-text">{{ $message }}</span>@enderror</label>
            </div>

            <div>
                <div class="ei-parts-head"><div class="ei-h">Parts</div>@if ($open)<button type="button" class="ei-link" id="addPart">+ Add part</button>@endif</div>
                <div id="parts">
                    @foreach ($rows as $row)
                        @include('enquiry::enquiries._intake_part', ['key' => $row['key'], 'it' => $row['item'], 'num' => $loop->iteration, 'multi' => $multi])
                    @endforeach
                </div>
                @error('items')<span class="error-text">{{ $message }}</span>@enderror
            </div>
        </div>

        <aside class="ei-side">
            <div class="ei-preview" id="filePreview">
                @if ($preview && $preview->isImage())<img src="{{ route('attachments.download', $preview) }}" alt="{{ $preview->original_name }}">
                @elseif ($preview)<iframe src="{{ route('attachments.download', $preview) }}" title="{{ $preview->original_name }}"></iframe>
                @else<code>{{ $files->isEmpty() ? 'No drawing yet' : 'No preview for these files' }}</code>@endif
            </div>
            <div id="fileInfo" hidden></div>
            <div class="ei-files" id="fileStrip" @if ($files->isEmpty()) hidden @endif>
                @foreach ($files as $f)<button type="button" class="ei-file @if ($preview && $f->is($preview)) on @endif" data-url="{{ route('attachments.download', $f) }}" data-view="{{ $f->isImage() ? 'image' : ($f->isInlineViewable() ? 'frame' : (in_array(strtolower(pathinfo($f->original_name, PATHINFO_EXTENSION)), ['stl', 'stp', 'step'], true) ? 'model-'.(strtolower(pathinfo($f->original_name, PATHINFO_EXTENSION)) === 'stl' ? 'stl' : 'step') : '')) }}" data-name="{{ $f->original_name }}" title="{{ $f->original_name }}"><span class="ei-file-ico">{{ $f->isImage() ? '🖼' : ($f->isInlineViewable() ? '📄' : (in_array(strtolower(pathinfo($f->original_name, PATHINFO_EXTENSION)), ['stl', 'stp', 'step'], true) ? '🧊' : '📎')) }}</span><span class="ei-file-name">{{ $f->original_name }}</span></button>@endforeach
            </div>
            @if ($open)
                <div class="ei-upload">
                    <label class="ei-f">Add drawing, model, photos or the customer's email<input type="file" name="files[]" multiple data-max-kb="{{ \App\Support\Uploads::effectiveMaxKb() }}"></label>
                    @error('files')<span class="error-text">{{ $message }}</span>@enderror @error('files.*')<span class="error-text">{{ $message }}</span>@enderror
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

            <div data-kept hidden>
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

<template id="partTemplate">@include('enquiry::enquiries._intake_part', ['key' => '__I__', 'it' => null, 'num' => '__N__', 'multi' => true])</template>

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

    function applyKind() {
        var k = kind();
        form.querySelectorAll('#kindChips .ei-chip').forEach(function (c) { c.classList.toggle('on', c.querySelector('input').checked); });
        document.getElementById('noteBlock').hidden = !NOTES[k];
        if (NOTES[k]) document.getElementById('noteLabel').textContent = NOTES[k];
        parts.querySelectorAll('[data-own]').forEach(function (g) { toggle(g, OWN.indexOf(k) >= 0); });
        var list = document.getElementById('adviceList');
        if (list) { list.innerHTML = ''; ((SC[k] || {}).advice || ['Choose the kind of request to see what usually follows.']).forEach(function (a) { var li = document.createElement('li'); li.textContent = '→ ' + a; list.appendChild(li); }); }
        var cat = document.getElementById('file_category');
        if (cat && SC[k] && !form.dataset.catTouched) cat.value = SC[k].cat;
    }
    function applyPart(p) {
        var famSel = p.querySelector('[data-family]'), fam = famSel ? famSel.value : '';
        p.querySelectorAll('[data-only-family]').forEach(function (c) {
            var box = c.querySelector('input'), fits = fam === c.dataset.onlyFamily || (fam === '' && box.checked);
            if (!fits) box.checked = false;
            c.hidden = !fits; box.disabled = !fits;
        });
        p.querySelectorAll('[data-quality] .ei-chip:not(.ei-chip-other)').forEach(function (c) { c.classList.toggle('on', c.querySelector('input').checked); });
        var ppap = p.querySelector('[data-ppap]'), lvl = p.querySelector('[data-ppap-level]');
        if (ppap && lvl) toggle(lvl, ppap.checked);
        var sel = p.querySelector('[data-material]'), box = p.querySelector('[data-material-new]');
        if (sel && box) toggle(box, sel.value === '__new__');
    }
    function renumber() {
        var all = parts.querySelectorAll('[data-part]');
        all.forEach(function (p, i) { p.querySelectorAll('[data-num]').forEach(function (n) { n.textContent = i + 1; }); });
    }
    function customerPpap() { var s = document.getElementById('customer_id'), o = s && s.selectedIndex > 0 ? s.options[s.selectedIndex] : null; return o ? o.dataset.ppap : ''; }
    function presetPpap(p) {
        var level = customerPpap(), box = p.querySelector('[data-ppap]'), sel = p.querySelector('[name$="[ppap_level]"]');
        if (!level || !box || p.dataset.ppapTouched) return;
        box.checked = true; if (sel) sel.value = level;
    }
    function applyCustomer() { parts.querySelectorAll('[data-part][data-new]').forEach(function (p) { presetPpap(p); applyPart(p); }); }

    form.addEventListener('change', function (ev) {
        var t = ev.target, p = t.closest && t.closest('[data-part]');
        if (t.name === 'customer_id') applyCustomer();
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
    // Files: saved and newly chosen ones are icons under the preview; clicking one shows it above. Chosen files add up across picks.
    var fileIn = form.querySelector('[name="files[]"]'), strip = document.getElementById('fileStrip'), pane = document.getElementById('filePreview'), info = document.getElementById('fileInfo'), picked = [];
    function showFile(b) {
        strip.querySelectorAll('.ei-file').forEach(function (x) { x.classList.toggle('on', x === b); });
        if (pane._drop) { pane._drop(); pane._drop = null; }
        pane.innerHTML = '';
        var v = b.dataset.view, el, token = pane._token = {};
        if (/^model-/.test(v)) {
            pane.innerHTML = '<code>Loading 3D model…</code>';
            import('{{ asset('js/viewer/model-viewer.js') }}?v={{ @filemtime(public_path('js/viewer/model-viewer.js')) }}').then(function (m) { return m.mount(pane, b.dataset.url, v.slice(6), info); }).then(function (drop) {
                if (pane._token !== token) { drop(); return; }
                pane.querySelector('code').remove(); pane._drop = drop;
            }).catch(function (err) { console.error('3D preview failed', err); if (pane._token === token) pane.innerHTML = '<code>Could not show ' + b.dataset.name.replace(/</g, '&lt;') + '</code>'; });
            return;
        }
        if (v === 'image') { el = document.createElement('img'); el.src = b.dataset.url; el.alt = b.dataset.name; }
        else if (v === 'frame') { el = document.createElement('iframe'); el.src = b.dataset.url; el.title = b.dataset.name; }
        else { el = document.createElement('code'); el.textContent = 'No preview for ' + b.dataset.name; }
        pane.appendChild(el);
    }
    function syncPicked() {
        var dt = new DataTransfer();
        picked.forEach(function (x) { dt.items.add(x.file); });
        fileIn.files = dt.files;
        strip.querySelectorAll('.ei-file-new').forEach(function (b) { b.remove(); });
        picked.forEach(function (x, i) {
            var t = x.file.type, ext = x.file.name.split('.').pop().toLowerCase(), view = /^image\/(png|jpeg|webp)$/.test(t) ? 'image' : (t === 'application/pdf' ? 'frame' : (ext === 'stl' ? 'model-stl' : (ext === 'stp' || ext === 'step' ? 'model-step' : '')));
            var b = document.createElement('button'); b.type = 'button'; b.className = 'ei-file ei-file-new'; b.title = x.file.name;
            b.dataset.url = x.url; b.dataset.view = view; b.dataset.name = x.file.name;
            var ico = document.createElement('span'); ico.className = 'ei-file-ico'; ico.textContent = view === 'image' ? '🖼' : (view === 'frame' ? '📄' : (view ? '🧊' : '📎'));
            var nm = document.createElement('span'); nm.className = 'ei-file-name'; nm.textContent = x.file.name;
            var rm = document.createElement('span'); rm.className = 'ei-file-x'; rm.setAttribute('data-drop', i); rm.setAttribute('aria-label', 'Remove ' + x.file.name); rm.textContent = '×';
            b.append(ico, nm, rm); strip.appendChild(b);
        });
        strip.hidden = !strip.children.length;
    }
    if (fileIn && strip) {
        fileIn.addEventListener('change', function () {
            Array.from(fileIn.files).forEach(function (f) {
                if (!picked.some(function (x) { return x.file.name === f.name && x.file.size === f.size && x.file.lastModified === f.lastModified; })) picked.push({ file: f, url: URL.createObjectURL(f) });
            });
            syncPicked();
            var last = strip.querySelector('.ei-file-new:last-child'); if (last) showFile(last);
        });
        strip.addEventListener('click', function (ev) {
            var x = ev.target.closest('[data-drop]'), b = ev.target.closest('.ei-file');
            if (x) { URL.revokeObjectURL(picked.splice(+x.getAttribute('data-drop'), 1)[0].url); syncPicked(); var first = strip.querySelector('.ei-file'); if (first) showFile(first); else pane.innerHTML = '<code>No drawing yet</code>'; fileIn.dispatchEvent(new Event('input', { bubbles: true })); return; }
            if (b) showFile(b);
        });
    }
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
    var FULL_SAVE = {{ $e && $status !== 'NEW' ? 'true' : 'false' }}, HAS_FILES = {{ $files->isNotEmpty() ? 'true' : 'false' }};
    var touched = new Set(), shown = new Set();
    function live(el) { return el && !el.disabled && !el.closest('[hidden]'); }
    function blank(el) { return !el || String(el.value).trim() === ''; }
    function numberMessage(el) {
        var v = el.validity;
        if (v.badInput) return 'Enter a number.';
        if (v.rangeUnderflow) return 'Must be at least ' + el.min + '.';
        if (v.rangeOverflow) return 'Must be at most ' + el.max + '.';
        if (v.stepMismatch) return 'Whole numbers only.';
        return '';
    }
    // Every problem on the page as {el, msg, soft}. Soft ones only stop sending, not saving a draft.
    function problems() {
        var out = [], k = kind(), cust = document.getElementById('customer_id'), note = form.querySelector('[name="summary"]');
        function add(el, msg, soft) { out.push({ el: el, msg: msg, soft: !!soft }); }
        if (live(cust) && blank(cust)) add(cust, 'Choose the customer.');
        if (!k) add(form.querySelector('#kindChips'), 'Choose the kind of request.');
        if (live(note) && NOTES[k] && blank(note)) add(note, 'Write it in the box: ' + NOTES[k], true);
        var files = form.querySelector('[name="files[]"]'), hasFiles = HAS_FILES || (files && files.files.length > 0);
        parts.querySelectorAll('[data-part]').forEach(function (p) {
            var rm = p.querySelector('[name$="[remove]"]');
            if (rm && rm.checked) return;
            var d = p.querySelector('[name$="[description]"]'), prod = p.querySelector('[name$="[product_id]"]'), mat = p.querySelector('[data-material]'),
                matNew = p.querySelector('[name$="[material_new]"]'), ppap = p.querySelector('[data-ppap]'), lvl = p.querySelector('[name$="[ppap_level]"]'),
                ref = p.querySelector('[name$="[drawing_ref]"]');
            if (live(d) && blank(d)) add(d, 'Name each part.');
            if (live(prod) && blank(prod)) add(prod, 'Choose which of our parts it is.', true);
            if (live(matNew) && mat && mat.value === '__new__' && blank(matNew)) add(matNew, 'Enter the new material grade.', true);
            if (live(lvl) && ppap && ppap.checked && blank(lvl)) add(lvl, 'Choose the PPAP level.', true);
            if (live(ref) && (SC[k] || {}).drawing && blank(ref) && !hasFiles) add(ref, 'Give the drawing number, or attach the drawing or model.', true);
            p.querySelectorAll('input[type=number]').forEach(function (n) { var m = live(n) && n.value !== '' ? numberMessage(n) : ''; if (m) add(n, m); });
        });
        return out;
    }
    function host(el) { return el.id === 'kindChips' ? el : (el.closest('label, .ei-f') || el.parentNode); }
    function mark(el, msg) {
        var h = host(el), box = h.querySelector(':scope > [data-live-err]');
        if (msg) {
            if (!box) { box = document.createElement('span'); box.className = 'error-text'; box.setAttribute('data-live-err', ''); box.setAttribute('role', 'alert'); h.appendChild(box); }
            box.textContent = msg; el.setAttribute('aria-invalid', 'true');
        } else if (box) { box.remove(); el.removeAttribute('aria-invalid'); }
    }
    function check(all) {
        var list = problems(), now = new Set();
        list.forEach(function (x) { if (all || touched.has(x.el)) { mark(x.el, x.msg); now.add(x.el); } });
        shown.forEach(function (el) { if (!now.has(el)) mark(el, ''); });
        shown = now;
        return list;
    }
    function touch(ev) {
        var t = ev.target;
        if (t.closest && t.closest('.ei-bar')) return;
        touched.add(t);
        if (t.name === 'source_type') touched.add(form.querySelector('#kindChips'));
        if (t.matches && t.matches('[data-ppap]')) { var l = t.closest('[data-part]').querySelector('[name$="[ppap_level]"]'); if (l && !l.disabled) touched.add(l); }
        if (t.name === 'files[]') parts.querySelectorAll('[name$="[drawing_ref]"]').forEach(function (r) { touched.add(r); });
        check(false);
    }
    form.addEventListener('input', touch); form.addEventListener('change', function (ev) { setTimeout(function () { touch(ev); }, 0); }); form.addEventListener('focusout', touch);
    form.addEventListener('submit', function (ev) {
        var action = ev.submitter ? ev.submitter.value : '', full = action === 'send' || (action === 'save' && FULL_SAVE);
        var blocking = check(true).filter(function (x) { return full || !x.soft; });
        if (blocking.length) {
            ev.preventDefault();
            var el = blocking[0].el; try { el.focus(); } catch (e) {} host(el).scrollIntoView({ block: 'center', behavior: 'smooth' });
            return;
        }
        if (ev.submitter && ev.submitter.name && ev.submitter.getAttribute('form') === null) {
            var h = document.createElement('input'); h.type = 'hidden'; h.name = ev.submitter.name; h.value = ev.submitter.value; form.appendChild(h);
        }
        form.querySelectorAll('.ei-actions button[type=submit]').forEach(function (b) { b.disabled = true; });
    });
    applyKind(); applyCustomer();
    parts.querySelectorAll('[data-part]').forEach(applyPart);
})();
</script>
@endsection
