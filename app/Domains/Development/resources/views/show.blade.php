@extends('layouts.app')
@section('title', $d->code)
@section('breadcrumb')<a href="{{ route('developments.index') }}">Pattern developments</a> / {{ $d->code }} @endsection
@section('content')
@php
    $cls = ['OPEN' => 'status-in-progress', 'DONE' => 'status-completed', 'CANCELLED' => 'status-cancelled'];
    $info = $d->typeInfo(); $stages = $d->stages(); $next = $d->nextStageName();
    $approves = $d->isOpen() && $next && $d->stage_index + 2 === count($stages) ? $d->draftRevision() : null;
    $tools = $d->tools; $many = $tools->count() > 1;
    $stuck = $d->isOpen() ? $d->toolsInTheWay() : collect();
    $gateRule = \App\Domains\Development\Support\DevelopmentRules::get('tool_gate');
    $blocked = $stuck->isNotEmpty() && $gateRule === 'BLOCK';
    $gateNeeds = $d->gateStatus() ? strtolower(config('pattern_developments.tool_statuses.'.$d->gateStatus())) : '';
    $pills = ['NOT_STARTED' => '', 'BUILDING' => 'status-in-progress', 'BUILT' => 'status-in-progress', 'TRIAL_PASSED' => 'status-completed', 'REWORK' => 'status-cancelled', 'NOT_NEEDED' => ''];
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
        @if ($d->isOpen() && $next)<form method="POST" action="{{ route('developments.advance', $d) }}" style="display:inline">@csrf<button class="btn btn-primary" type="submit" @disabled($blocked)>Move to {{ $next }}</button></form>@endif</div>
</div>
@if ($d->isOpen() && $stuck->isNotEmpty() && $gateRule !== 'OFF' && ! session('gate_warn'))
<div class="alert alert-{{ $blocked ? 'error' : 'warning' }}">{{ $stuck->pluck('label')->implode(', ') }} {{ $stuck->count() > 1 ? 'have' : 'has' }} not reached {{ $gateNeeds }} yet.@if ($blocked) A rule in settings holds the development here.@endif</div>
@endif
@if (session('gate_warn'))
<div class="alert alert-warning"><div>{{ session('gate_warn') }} Moving on is logged as an override with your name.</div>
    <form method="POST" action="{{ route('developments.advance', $d) }}" style="margin-top:8px">@csrf<input type="hidden" name="override" value="1"><button class="btn btn-primary" type="submit">Move anyway</button> <a class="btn" href="{{ route('developments.show', $d) }}">Cancel</a></form></div>
@endif
@error('gate')<div class="alert alert-error">{{ $message }}</div>@enderror
@if ($d->isOpen() && $d->stage_index > 0)
<form method="POST" action="{{ route('developments.back', $d) }}" style="margin-bottom:14px">@csrf
    <div style="display:flex;gap:8px">
    <input name="reason" maxlength="300" required placeholder="Why it goes back, e.g. trial found shrinkage at the boss" style="flex:1" aria-label="Reason to go back" value="{{ old('reason') }}">
    <button class="btn" type="submit">Back to {{ $stages[$d->stage_index - 1] }}</button></div>
    @if ($many)<div class="small" style="margin-top:8px"><span class="muted">Tools to rework (the others keep their status):</span>
        @foreach ($tools as $t)<label style="margin-left:12px"><input type="checkbox" name="tools[]" value="{{ $t->id }}" @checked(in_array($t->id, old('tools', [])))> {{ $t->label }} <span class="muted">{{ $t->statusName() }}</span></label>@endforeach</div>@endif
</form>
@error('reason')<div class="alert alert-error">{{ $message }}</div>@enderror
@endif
@if ($approves)<div class="alert alert-info">Moving to {{ $next }} also approves revision {{ $approves->revision }} of {{ $d->product->code }}, which orders for this part wait on.</div>@endif
<div class="dv-bar">@foreach ($stages as $i => $name)<div class="{{ $d->status === 'DONE' || $i < $d->stage_index ? 'done' : ($i === $d->stage_index ? 'now' : '') }}">{{ $name }}@if ($many && ($g = \App\Domains\Development\Models\PatternDevelopment::gateFor($name)))<span style="font-weight:400;display:block">{{ $tools->filter(fn ($t) => $t->reached($g))->count() }} of {{ $tools->count() }} {{ $g === 'BUILT' ? 'built' : 'passed trial' }}</span>@endif</div>@endforeach</div>
<div class="panel"><div class="panel-header"><h2>Details</h2><span class="muted small">{{ $info['note'] }}</span></div><div class="panel-body">
<dl class="detail-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px 24px;margin:0">
    <div><dt class="muted small">Customer</dt><dd style="margin:0">{{ $d->customer?->name ?? '—' }}</dd></div>
    <div><dt class="muted small">Enquiry</dt><dd style="margin:0">@if ($d->enquiry)<a href="{{ route('enquiries.show', $d->enquiry) }}">{{ $d->enquiry->code }}</a>@else — @endif</dd></div>
    <div><dt class="muted small">Part</dt><dd style="margin:0">@if ($d->product)<a href="{{ route('products.show', $d->product) }}">{{ $d->product->code }} {{ $d->product->name }}</a>@else — @endif</dd></div>
    <div><dt class="muted small">Owner</dt><dd style="margin:0">{{ $d->owner_name ?: '—' }}</dd></div>
    <div><dt class="muted small">Due</dt><dd style="margin:0">{{ $d->due_on?->displayDate() ?? '—' }}</dd></div>
    @if ($d->reason)<div><dt class="muted small">Reason</dt><dd style="margin:0">{{ config('pattern_developments.reasons.'.$d->reason) }}</dd></div>@endif
    @if ($d->who_pays)<div><dt class="muted small">Who pays</dt><dd style="margin:0">{{ config('pattern_developments.who_pays.'.$d->who_pays) }}</dd></div>@endif
    @if ($d->drawing_change)<div><dt class="muted small">Drawing change</dt><dd style="margin:0">{{ $d->drawing_change }}</dd></div>@endif
</dl>
@if ($d->notes)<p style="margin-top:14px">{{ $d->notes }}</p>@endif
</div></div>
@php
    $amount = fn ($v) => $d->cost_currency ? $d->cost_currency.' '.\App\Support\Formats::number($v, 2) : \App\Support\Formats::money($v);
    $quotedSum = $tools->whereNotNull('quoted_cost')->sum('quoted_cost');
    $actualSum = $tools->whereNotNull('actual_cost')->isEmpty() ? null : $tools->sum('actual_cost');
    $over = $d->quoted_tooling_cost !== null && $actualSum !== null ? $actualSum - $d->quoted_tooling_cost : null;
@endphp
<div class="panel"><div class="panel-header"><h2>Tooling · {{ $tools->count() }} {{ \Illuminate\Support\Str::plural('tool', $tools->count()) }}</h2>
    @if ($d->isOpen())<details style="position:relative"><summary class="btn">+ Add tool</summary>
        <form method="POST" action="{{ route('developments.tools.store', $d) }}" class="form-grid" style="position:absolute;right:0;z-index:5;background:#fff;border:1px solid var(--border);border-radius:8px;padding:14px;width:420px;grid-template-columns:1fr 1fr;gap:10px">@csrf
            <div class="field"><label for="tl-kind">Kind</label><select id="tl-kind" name="kind">@foreach (config('pattern_developments.tool_kinds') as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
            <div class="field"><label for="tl-route">Route</label><select id="tl-route" name="route">@foreach (config('pattern_developments.tool_routes') as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
            <div class="field field-full" style="grid-column:1/-1"><label for="tl-label">Name</label><input id="tl-label" name="label" maxlength="200" required placeholder="e.g. Volute core box"></div>
            <div class="field"><label for="tl-by">Made by</label><select id="tl-by" name="made_by">@foreach (config('pattern_developments.pattern_by') as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
            <div class="field"><label for="tl-maker">Maker</label><input id="tl-maker" name="maker" maxlength="150"></div>
            <div class="field"><label for="tl-pays">Pays</label><select id="tl-pays" name="who_pays"><option value="">—</option>@foreach (config('pattern_developments.who_pays') as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
            <div class="field"><label for="tl-quoted">Quoted</label><input id="tl-quoted" name="quoted_cost" type="number" step="0.01" min="0"></div>
            <div style="grid-column:1/-1"><button class="btn btn-primary" type="submit">Add tool</button></div>
        </form></details>@endif</div>
<div class="panel-body">
    @if ($tools->isEmpty())<p class="muted">No tools yet. Add the pattern, core box or gauge this development will make or change.</p>@else
    <div style="overflow-x:auto"><table class="table"><thead><tr><th>Tool</th><th>Route</th><th>Made by</th><th>Pays</th><th>Quoted</th><th>Actual</th><th>Due</th><th>Status</th><th></th></tr></thead><tbody>
    @foreach ($tools as $t)
        <tr>
            <td><strong>{{ config('pattern_developments.tool_kinds.'.$t->kind) }}</strong> {{ $t->label }}@if ($t->pattern)<br><a class="small" href="{{ route('patterns.show', $t->pattern) }}">{{ $t->pattern->code }}</a>@endif</td>
            <td>{{ config('pattern_developments.tool_routes.'.$t->route) }}</td>
            <td>{{ config('pattern_developments.pattern_by.'.$t->made_by) }}@if ($t->maker)<br><span class="muted small">{{ $t->maker }}</span>@endif</td>
            <td>{{ $t->who_pays ? config('pattern_developments.who_pays.'.$t->who_pays) : '—' }}</td>
            <td>{{ $t->quoted_cost !== null ? $amount($t->quoted_cost) : '—' }}</td>
            <td>@if ($d->isOpen())<form method="POST" action="{{ route('developments.tools.update', [$d, $t]) }}" id="tool-{{ $t->id }}">@csrf @method('PUT')<input name="actual_cost" type="number" step="0.01" min="0" value="{{ $t->actual_cost }}" style="width:120px" aria-label="Actual cost of {{ $t->label }}"></form>@else{{ $t->actual_cost !== null ? $amount($t->actual_cost) : '—' }}@endif</td>
            <td>{{ $t->due_on?->displayDate() ?? '—' }}</td>
            <td>@if ($d->isOpen())<select name="status" form="tool-{{ $t->id }}" aria-label="Status of {{ $t->label }}" onchange="this.form.requestSubmit()">@foreach (config('pattern_developments.tool_statuses') as $k => $l)<option value="{{ $k }}" @selected($t->status === $k)>{{ $l }}</option>@endforeach</select>@else<span class="status {{ $pills[$t->status] ?? '' }}">{{ $t->statusName() }}</span>@endif
                @if ($t->status === 'REWORK' && $t->status_note)<br><span class="muted small">{{ $t->status_note }}</span>@endif</td>
            <td>@if ($d->isOpen())<button class="btn" type="submit" form="tool-{{ $t->id }}">Save</button>
                <form method="POST" action="{{ route('developments.tools.destroy', [$d, $t]) }}" style="display:inline" onsubmit="return confirm('Remove this tool?')">@csrf @method('DELETE')<button class="btn" type="submit" aria-label="Remove {{ $t->label }}">Remove</button></form>@endif</td>
        </tr>
    @endforeach
    </tbody></table></div>
    <p style="margin:12px 0 0">Total quoted {{ $quotedSum ? $amount($quotedSum) : '—' }} · actual {{ $actualSum !== null ? $amount($actualSum) : '—' }}
        @if ($d->quoted_tooling_cost !== null) · Allowed in quote {{ $amount($d->quoted_tooling_cost) }}@endif
        @if ($over !== null) · <span @if ($over > 0) style="color:#b3352c" @else class="muted" @endif>{{ $over > 0 ? 'Over the quote by '.$amount($over) : 'Within the quote by '.$amount(-$over) }}</span>@endif</p>
    @endif
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
