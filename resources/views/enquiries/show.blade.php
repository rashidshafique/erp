@extends('layouts.app')
@section('title', $e->code)
@section('breadcrumb')<a href="{{ route('enquiries.index') }}">Enquiries</a> / {{ $e->code }} @endsection
@section('content')
<style>.ca-wrap td, .ca-wrap th { white-space: normal; vertical-align: top; } input[type=checkbox] { accent-color: var(--accent); } .item-card { margin-bottom: 12px; } .item-card details { margin-top: 10px; }</style>
@php
    $cls = ['NEW' => 'status-on-hold', 'FEASIBILITY' => 'status-on-hold', 'QUOTED' => 'status-in-progress', 'WON' => 'status-completed', 'LOST' => 'status-cancelled', 'CANCELLED' => 'status-cancelled'];
    $qcls = ['DRAFT' => 'status-on-hold', 'ISSUED' => 'status-in-progress', 'ACCEPTED' => 'status-completed', 'REJECTED' => 'status-cancelled', 'EXPIRED' => 'status-cancelled', 'SUPERSEDED' => 'status-cancelled'];
    $fcls = ['PENDING' => 'status-on-hold', 'OK' => 'status-completed', 'CONDITIONAL' => 'status-in-progress', 'NOT_FEASIBLE' => 'status-cancelled'];
    $open = $e->isOpen();
@endphp
<div class="page-heading">
    <div><h1>{{ $e->title }}</h1><p>{{ $e->code }} · {{ $e->customer?->name }} · {{ \App\Models\Enquiry::SOURCES[$e->source_type] }} · <span class="status {{ $cls[$e->status] ?? '' }}">{{ $e->status }}</span></p></div>
    <div class="actions"><a class="btn" href="{{ route('enquiries.index') }}">All enquiries</a></div>
</div>
@if (session('error'))<div class="alert alert-error">{{ session('error') }}</div>@endif

<div class="tab-marker" data-tab-start="Summary"></div>
@php $guide = \App\Services\Quotation\EnquiryGuide::steps($e); @endphp
<div class="panel"><div class="panel-header"><h2>What happens next</h2><span class="muted small">{{ config('enquiry_scenarios.'.$e->source_type.'.label') }}</span></div><div class="panel-body">
<ul style="list-style:none;margin:0;padding:0">
@foreach ($guide as $g)<li style="padding:3px 0">@if ($g['done'] === true)<span style="color:var(--accent)">&#10003;</span> {{ $g['label'] }}@elseif ($g['done'] === false)<span class="muted">&#9675;</span> {{ $g['label'] }}@else<span class="muted">&rarr;</span> <span class="muted">{{ $g['label'] }}</span>@endif</li>@endforeach
</ul></div></div>
<div class="panel"><div class="panel-header"><h2>Request</h2></div><div class="panel-body">
@if ($open)
<form method="POST" action="{{ route('enquiries.update', $e) }}">@csrf @method('PUT')
<div class="form-grid">
    <div class="field"><label>Title</label><input name="title" value="{{ old('title', $e->title) }}" required></div>
    <div class="field"><label>Kind</label><select name="source_type">@foreach (\App\Models\Enquiry::SOURCES as $k => $l)<option value="{{ $k }}" @selected(old('source_type', $e->source_type) === $k)>{{ $l }}</option>@endforeach</select></div>
    <div class="field"><label>Quote needed by</label><input type="date" name="due_on" value="{{ old('due_on', $e->due_on?->toDateString()) }}"></div>
    <div class="field"><label>Their reference</label><input name="customer_reference" value="{{ old('customer_reference', $e->customer_reference) }}"></div>
    <div class="field"><label>Contact</label><input name="contact_name" value="{{ old('contact_name', $e->contact_name) }}"></div>
    <div class="field field-full"><label>What they said</label><textarea name="summary" rows="3">{{ old('summary', $e->summary) }}</textarea></div>
</div>
<p><button class="btn btn-primary" type="submit">Save</button></p>
</form>
@else
<p>{{ $e->summary ?: 'No summary.' }}</p>
<p class="muted">Closed {{ $e->closed_at?->displayDate() }}@if ($e->lost_reason) — {{ $e->lost_reason }}@endif</p>
@endif
</div></div>
@if ($open)
<div class="panel"><div class="panel-header"><h2>Close this enquiry</h2></div><div class="panel-body">
<form method="POST" action="{{ route('enquiries.lost', $e) }}" onsubmit="return confirm('Close this enquiry? Open quotations will be marked rejected.')">@csrf
<div class="form-grid"><div class="field"><label>Outcome</label><select name="outcome"><option value="LOST">Lost to another supplier or price</option><option value="CANCELLED">Customer cancelled</option></select></div>
<div class="field"><label>Reason</label><input name="lost_reason" required placeholder="e.g. 12% above competitor"></div></div>
<p><button class="btn btn-danger" type="submit">Close enquiry</button></p></form>
</div></div>
@endif

<div class="tab-marker" data-tab-start="Parts & feasibility"></div>
<div class="ca-wrap">
@forelse ($e->items as $it)
@php $a = $it->analysis; $severe = $a && collect($a->result['hotspots'] ?? [])->contains('role', 'ISOLATED_SEVERE'); @endphp
<div class="panel item-card"><div class="panel-header"><h2>{{ $it->line_no }}. {{ $it->description }}</h2><span class="status {{ $fcls[$it->feasibility_status] ?? '' }}">{{ \App\Models\EnquiryItem::FEASIBILITY[$it->feasibility_status] }}</span></div>
<div class="panel-body">
    <p class="small muted">
        @if ($it->product)Product <a href="{{ route('products.show', $it->product) }}">{{ $it->product->code }}</a> · @endif
        @if ($it->drawing_ref)Drawing {{ $it->drawing_ref }}{{ $it->drawing_rev ? ' rev '.$it->drawing_rev : '' }} · @endif
        {{ $it->material_text ?: 'Material not stated' }} · {{ $it->weight_kg ? \App\Support\Formats::number($it->weight_kg, 2).' kg' : 'weight unknown' }} ·
        first batch {{ $it->first_batch_qty ?? '—' }}, yearly {{ $it->annual_qty ?? '—' }}@if ($it->target_price) · target {{ \App\Support\Formats::number($it->target_price, 2) }}@endif
    </p>
    <p>
        @if ($a)
            <strong>Analysis <a href="{{ route('casting-analyses.show', $a) }}">{{ $a->code }}</a></strong>: score <strong>{{ $a->score }}</strong> ({{ $a->score_letter }}){!! $severe ? ' · <span class="status status-cancelled">cut-off hotspot</span>' : '' !!}
        @else
            <span class="muted">No casting analysis yet.</span>
        @endif
        @if ($open)
            · <a href="{{ route('casting-analyses.create', ['enquiry_item' => $it->id]) }}">Analyse a model</a>
        @endif
    </p>
    @if ($open)
    <form method="POST" action="{{ route('enquiries.items.analysis', [$e, $it]) }}" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">@csrf
        <select name="casting_analysis_id" style="max-width:320px"><option value="">— no analysis —</option>@foreach ($analyses as $an)<option value="{{ $an->id }}" @selected($it->casting_analysis_id === $an->id)>{{ $an->code }} — {{ $an->name }} (score {{ $an->score }})</option>@endforeach</select>
        <button class="btn" type="submit">Link existing analysis</button>
    </form>
    <hr style="border:0;border-top:1px solid var(--border);margin:12px 0">
    <form method="POST" action="{{ route('enquiries.items.feasibility', [$e, $it]) }}">@csrf
        <div class="form-grid">
            <div class="field"><label>Feasibility decision</label><select name="feasibility_status">@foreach (['OK', 'CONDITIONAL', 'NOT_FEASIBLE'] as $s)<option value="{{ $s }}" @selected($it->feasibility_status === $s)>{{ \App\Models\EnquiryItem::FEASIBILITY[$s] }}</option>@endforeach</select></div>
            <div class="field field-full"><label>Conditions or reason</label><textarea name="feasibility_notes" rows="2" placeholder="Required unless plainly feasible. e.g. chill the boss, customer to relax wall to 6 mm">{{ $it->feasibility_notes }}</textarea></div>
        </div>
        <p><button class="btn btn-primary" type="submit">Record decision</button></p>
    </form>
    <details><summary>Edit part details</summary>
        <form method="POST" action="{{ route('enquiries.items.update', [$e, $it]) }}">@csrf @method('PUT')
            @include('enquiries._item_fields', ['item' => $it, 'products' => $products])
            <p><button class="btn btn-primary" type="submit">Save part</button></p>
        </form>
        <form method="POST" action="{{ route('enquiries.items.destroy', [$e, $it]) }}" onsubmit="return confirm('Remove this part?')">@csrf @method('DELETE')<button class="btn btn-danger" type="submit">Remove part</button></form>
    </details>
    @elseif ($it->feasibility_notes)
        <p>{{ $it->feasibility_notes }}</p>
    @endif
</div></div>
@empty
<div class="panel"><div class="empty-state">No parts yet. Add what the customer wants made.</div></div>
@endforelse
@if ($open)
<div class="panel"><div class="panel-header"><h2>Add a part</h2></div><div class="panel-body"><details @if ($e->items->isEmpty()) open @endif><summary>{{ $e->items->isEmpty() ? 'Part details' : 'Add another part' }}</summary>
    <form method="POST" action="{{ route('enquiries.items.store', $e) }}">@csrf
        @include('enquiries._item_fields', ['item' => null, 'products' => $products])
        <p><button class="btn btn-primary" type="submit">Add part</button></p>
    </form></details>
</div></div>
@endif
</div>

<div class="tab-marker" data-tab-start="Quotation"></div>
<div class="panel"><div class="panel-header"><h2>Quotations</h2>
    @if ($open)<form method="POST" action="{{ route('enquiries.quotations.store', $e) }}">@csrf<button class="btn btn-primary" type="submit">{{ $e->quotations->firstWhere('status', 'DRAFT') ? 'Open the draft' : ($e->quotations->isEmpty() ? 'Create quotation' : 'New version') }}</button></form>@endif</div>
@if ($e->quotations->isEmpty())
    <div class="empty-state">No quotation yet. Assess each part first, then create the quotation to price them.</div>
@else
    <div class="table-wrap"><table><thead><tr><th>Quotation</th><th>Version</th><th>Status</th><th>Lowest margin</th><th>Valid until</th><th></th></tr></thead><tbody>
    @foreach ($e->quotations as $q)
        <tr><td><strong>{{ $q->code }}</strong></td><td>{{ $q->version }}</td><td><span class="status {{ $qcls[$q->status] ?? '' }}">{{ $q->status }}</span></td>
        <td>{{ $q->lines->isEmpty() ? '—' : \App\Support\Formats::number($q->lines->min('effective_margin_pct'), 1).'%' }}</td><td class="small">{{ $q->valid_until?->displayDate() ?? '—' }}</td><td><a href="{{ route('quotations.show', $q) }}">Open →</a></td></tr>
    @endforeach
    </tbody></table></div>
@endif
</div>

<div class="tab-marker" data-tab-start="Files & history"></div>
@include('attachments._panel', ['type' => 'enquiry', 'id' => $e->id])
@include('audit._history', ['type' => 'Enquiry', 'id' => $e->id])
@endsection
