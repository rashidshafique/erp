@extends('layouts.app')
@section('title', $d->code)
@section('breadcrumb')<a href="{{ route('developments.index') }}">Pattern developments</a> / {{ $d->code }} @endsection
@section('content')
@php
    $cls = ['OPEN' => 'status-in-progress', 'DONE' => 'status-completed', 'CANCELLED' => 'status-cancelled'];
    $info = $d->typeInfo(); $stages = $d->stages(); $next = $d->nextStageName();
    $approves = $d->isOpen() && $next && $d->stage_index + 2 === count($stages) ? $d->draftRevision() : null;
@endphp
<style>
    .dv-bar { display: grid; grid-template-columns: repeat({{ count($stages) }}, minmax(0, 1fr)); gap: 6px; margin: 4px 0 18px; }
    .dv-bar > div { display: flex; flex-direction: column; gap: 6px; font-size: 12.5px; color: var(--muted); } .dv-bar > div::before { content: ''; height: 4px; border-radius: 2px; background: var(--border); }
    .dv-bar > div.done::before, .dv-bar > div.now::before { background: var(--accent); } .dv-bar > div.now { color: var(--text); font-weight: 600; }
    .dv-log { list-style: none; margin: 0; padding: 0; } .dv-log li { padding: 8px 0; border-bottom: 1px solid var(--border); display: flex; gap: 12px; justify-content: space-between; } .dv-log .when { color: var(--muted); font-size: 12px; white-space: nowrap; }
</style>
<div class="page-heading">
    <div><h1>{{ $d->title }}</h1><p>{{ $d->code }} · {{ $info['name'] }} · {{ $d->customer?->name }} · <span class="status {{ $cls[$d->status] ?? '' }}">{{ $d->status === 'OPEN' ? $d->stageName() : \App\Domains\Development\Models\PatternDevelopment::STATUSES[$d->status] }}</span></p></div>
    <div class="actions"><a class="btn" href="{{ route('developments.index') }}">All developments</a>
        @if ($d->isOpen() && $next)<form method="POST" action="{{ route('developments.advance', $d) }}" style="display:inline">@csrf<button class="btn btn-primary" type="submit">Move to {{ $next }}</button></form>@endif</div>
</div>
@if ($d->isOpen() && $d->stage_index > 0)
<form method="POST" action="{{ route('developments.back', $d) }}" style="display:flex;gap:8px;margin-bottom:14px">@csrf
    <input name="reason" maxlength="300" required placeholder="Why it goes back, e.g. trial found shrinkage at the boss" style="flex:1" aria-label="Reason to go back" value="{{ old('reason') }}">
    <button class="btn" type="submit">Back to {{ $stages[$d->stage_index - 1] }}</button></form>
@error('reason')<div class="alert alert-error">{{ $message }}</div>@enderror
@endif
@if ($approves)<div class="alert alert-info">Moving to {{ $next }} also approves revision {{ $approves->revision }} of {{ $d->product->code }}, which orders for this part wait on.</div>@endif
<div class="dv-bar">@foreach ($stages as $i => $name)<div class="{{ $d->status === 'DONE' || $i < $d->stage_index ? 'done' : ($i === $d->stage_index ? 'now' : '') }}">{{ $name }}</div>@endforeach</div>
<div class="panel"><div class="panel-header"><h2>Details</h2><span class="muted small">{{ $info['note'] }}</span></div><div class="panel-body">
<dl class="detail-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px 24px;margin:0">
    <div><dt class="muted small">Customer</dt><dd style="margin:0">{{ $d->customer?->name ?? '—' }}</dd></div>
    <div><dt class="muted small">Enquiry</dt><dd style="margin:0">@if ($d->enquiry)<a href="{{ route('enquiries.show', $d->enquiry) }}">{{ $d->enquiry->code }}</a>@else — @endif</dd></div>
    <div><dt class="muted small">Part</dt><dd style="margin:0">@if ($d->product)<a href="{{ route('products.show', $d->product) }}">{{ $d->product->code }} {{ $d->product->name }}</a>@else — @endif</dd></div>
    <div><dt class="muted small">Pattern</dt><dd style="margin:0">@if ($d->pattern)<a href="{{ route('patterns.show', $d->pattern) }}">{{ $d->pattern->code }} {{ $d->pattern->name }}</a>@else — @endif</dd></div>
    <div><dt class="muted small">Pattern by</dt><dd style="margin:0">{{ config('pattern_developments.pattern_by.'.$d->pattern_by) }}{{ $d->pattern_maker ? ' · '.$d->pattern_maker : '' }}</dd></div>
    <div><dt class="muted small">Owner</dt><dd style="margin:0">{{ $d->owner_name ?: '—' }}</dd></div>
    <div><dt class="muted small">Due</dt><dd style="margin:0">{{ $d->due_on?->displayDate() ?? '—' }}</dd></div>
    @if ($d->reason)<div><dt class="muted small">Reason</dt><dd style="margin:0">{{ config('pattern_developments.reasons.'.$d->reason) }}</dd></div>@endif
    @if ($d->who_pays)<div><dt class="muted small">Who pays</dt><dd style="margin:0">{{ config('pattern_developments.who_pays.'.$d->who_pays) }}</dd></div>@endif
    @if ($d->drawing_change)<div><dt class="muted small">Drawing change</dt><dd style="margin:0">{{ $d->drawing_change }}</dd></div>@endif
</dl>
@php
    $amount = fn ($v) => $d->cost_currency ? $d->cost_currency.' '.\App\Support\Formats::number($v, 2) : \App\Support\Formats::money($v);
    $over = $d->quoted_tooling_cost !== null && $d->actual_cost !== null ? $d->actual_cost - $d->quoted_tooling_cost : null;
@endphp
<form method="POST" action="{{ route('developments.cost', $d) }}" style="display:flex;gap:16px;align-items:flex-end;flex-wrap:wrap;margin-top:14px">@csrf @method('PUT')
    <div><div class="muted small">Tooling allowed in the quote</div><div>{{ $d->quoted_tooling_cost !== null ? $amount($d->quoted_tooling_cost) : '—' }}</div></div>
    <div><label class="muted small" for="dv-actual" style="display:block">Actual pattern cost</label><input id="dv-actual" name="actual_cost" type="number" step="0.01" min="0" value="{{ old('actual_cost', $d->actual_cost) }}" style="width:150px"></div>
    <button class="btn" type="submit">Save cost</button>
    @if ($over !== null)<span @if ($over > 0) style="color:#b3352c" @else class="muted" @endif>{{ $over > 0 ? 'Over the quote by '.$amount($over) : 'Within the quote by '.$amount(-$over) }}</span>@endif
</form>
@if ($d->notes)<p style="margin-top:14px">{{ $d->notes }}</p>@endif
</div></div>
<div class="panel"><div class="panel-header"><h2>Log</h2></div><div class="panel-body">
    <form method="POST" action="{{ route('developments.notes', $d) }}" style="display:flex;gap:8px;margin-bottom:10px">@csrf
        <input name="text" maxlength="2000" required placeholder="Add a note: what was agreed or sent" style="flex:1" aria-label="Note"><button class="btn" type="submit">Add note</button></form>
    <ul class="dv-log">@foreach ($d->events as $ev)<li><span>{{ $ev->text }}<span class="muted small"> · {{ $ev->user_name }}</span></span><span class="when">{{ $ev->created_at->displayDateTime() }}</span></li>@endforeach</ul>
</div></div>
@if ($d->isOpen())
<form method="POST" action="{{ route('developments.cancel', $d) }}" onsubmit="return confirm('Cancel {{ $d->code }}?')">@csrf<button class="btn btn-danger" type="submit">Cancel development</button></form>
@endif
@endsection
