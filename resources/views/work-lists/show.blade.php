@extends('layouts.app')
@section('title', $list->list_number)
@section('content')
@php
    $editable = in_array($list->status, ['DRAFT','ISSUED']);
    $groups = $list->items->groupBy('moulding_station_id');
@endphp
<div class="page-heading"><div><h1>{{ $list->list_number }}</h1><p>{{ $list->list_date->format('l d M Y') }} · {{ $list->shift }} shift · <span class="status">{{ $list->status }}</span>@if($list->issued_by) · issued by {{ $list->issued_by }}@endif</p></div>
<div class="actions"><a class="btn" href="{{ route('work-lists.print', $list) }}" target="_blank">Print for teams</a></div></div>
@if(session("success"))<div class="alert alert-success">{{ session("success") }}</div>@endif
@if($errors->any())<div class="alert alert-error">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif

@forelse($groups as $stationId => $items)
@php
    $st = $items->first()->station;
    $cap = $st->capacity();
    $now = $running[$stationId] ?? 0;
    $planned = $items->where('status', 'PLANNED')->count();
    $teams_here = $items->map(fn ($i) => $i->team ? $i->team->code.' — '.$i->team->lead_name : null)->filter()->unique()->implode(', ') ?: 'not set for this line';
    $customers = $items->map(fn ($i) => $i->productionOrder?->customerLabel() ?? 'unassigned')->unique()->implode(', ');
@endphp
<div class="panel">
    <div class="panel-header" style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">
        <h2 style="margin:0">{{ $st->code }} <span class="muted small" style="font-weight:500">{{ $st->name }}</span></h2>
        <span class="status">{{ $st->isAutomated() ? 'One pattern at a time' : 'Up to '.$cap.' pattern'.($cap > 1 ? 's' : '').' at once' }}</span>
        <span class="muted small">{{ $now }} of {{ $cap }} running · Team {{ $teams_here }} · {{ $items->pluck('sand_system')->unique()->implode(', ') }} · Customers: {{ $customers }}</span>
        @if($editable && $planned > 0)
            <details class="row-edit"><summary>Change sand</summary>
                <form method="POST" action="{{ route('work-lists.stations.change', [$list, $st]) }}" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:6px">@csrf
                    <select name="sand_system">@foreach($sands as $sd)<option @selected($items->first()->sand_system === $sd)>{{ $sd }}</option>@endforeach</select>
                    <label style="font-weight:400;display:flex;gap:5px;align-items:center"><input type="checkbox" name="accept_unsuited" value="1"> Accept where a plate is not suited</label>
                    <button class="btn btn-small">Apply to planned rows</button></form></details>
        @endif
        @if($list->status === 'ISSUED' && $planned > 0)
            <form method="POST" action="{{ route('work-lists.stations.start', [$list, $st]) }}" style="margin-left:auto">@csrf
                <button class="btn btn-primary" @disabled($now >= $cap)>{{ $st->isAutomated() ? 'Start next' : 'Start all planned' }}</button></form>
        @endif
    </div>
    <div class="table-wrap"><table><thead><tr><th>#</th><th>Customer</th><th>Order / product</th><th>Pattern</th><th>Sand</th><th>Moulds</th><th>Result</th><th>Status</th><th></th></tr></thead><tbody>
    @foreach($items as $i)<tr>
        <td>{{ $i->sequence }}</td>
        <td>{{ $i->productionOrder?->customerLabel() ?? '—' }}</td>
        <td>@if($i->productionOrder){{ $i->productionOrder->production_order_number }}@if($i->order_link === 'AUTO') <span class="status" title="Found by the system: the open order that needs this pattern">auto-linked</span>@elseif($i->order_link === 'STOCK') <span class="status">stock order</span>@endif
            <div class="muted small">{{ $i->productionOrder->product?->code }} · {{ $i->productionOrder->order_type }}</div>
            @else<span class="muted">No order yet</span><div class="muted small">@if($i->status === 'PLANNED')links to an open order, or a stock order is raised when the run starts @endif</div>
            @if($editable && $i->status === 'PLANNED')<form method="POST" action="{{ route('work-lists.items.link', [$list, $i]) }}">@csrf<button class="btn btn-small">Find open order</button></form>@endif @endif</td>
        <td>@include('work-lists._pattern-cell', ['revision' => $i->patternRevision])</td>
        <td>{{ $i->sand_system }}@if($i->sand_override)<div><span class="status" style="color:var(--danger,#b42318)" title="This plate is not listed as suited to this sand system; someone chose to run it anyway">sand mismatch accepted</span></div>@endif</td>
        <td>{{ $i->planned_moulds }}@if(($needed[$i->id] ?? null) !== null)<div class="muted small">order still needs ≈{{ $needed[$i->id] }}</div>@endif</td>
        <td>@if($i->run)<a href="{{ route('moulding-runs.show', $i->run) }}">{{ $i->run->accepted_mould_qty }} ok / {{ $i->run->damaged_mould_qty }} dmg</a>@else—@endif</td>
        <td>{{ $i->status }}</td>
        <td>@if($list->status === 'ISSUED' && $i->status === 'PLANNED')<form method="POST" action="{{ route('work-lists.items.start', [$list, $i]) }}">@csrf<button class="btn btn-primary">Start run</button></form>
        @elseif($editable && $i->status === 'PLANNED')<form method="POST" action="{{ route('work-lists.items.remove', [$list, $i]) }}">@csrf @method('DELETE')@include('partials.icon-button', ['type' => 'delete', 'word' => 'Remove', 'name' => $i->patternRevision->pattern->code])</form>@endif</td></tr>
    @endforeach</tbody></table></div>
</div>
@empty
<div class="panel"><div class="panel-body muted">Nothing on the list yet. Pick a station below and add the patterns it will mould.</div></div>
@endforelse

@if($editable)
<div class="panel"><div class="panel-header"><h2>Add work for a moulding line</h2></div><div class="panel-body">
<p class="muted small" style="margin-top:0">Pick the moulding line: its team is the line's own team, and its sand system fills in by itself and can be changed. Then list the pattern plates and how many moulds of each. An automated line takes one pattern at a time, so its rows run in order; a manual station can take several plates at once.</p>
<form method="POST" action="{{ route('work-lists.rows.add', $list) }}" id="add-work">@csrf
<div class="form-grid">
@include('partials.quick-dialog', ['id' => 'qd-station', 'title' => 'Add a station', 'action' => route('moulding-teams.stations.store'), 'submit' => 'Add station', 'fields' => 'moulding-teams._quick-station'])
@include('partials.quick-dialog', ['id' => 'qd-sand', 'title' => 'Add a sand system', 'action' => route('sand-systems.store'), 'submit' => 'Add sand system', 'fields' => 'sand-systems._quick-fields'])
<label>Moulding line <a href="{{ route('moulding-teams.index') }}" class="qd-add" data-quick="qd-station" data-quick-target="aw-station">+ Add</a><select name="moulding_station_id" id="aw-station" required>@foreach($stations as $s)<option value="{{ $s->id }}" data-team="{{ $teamOf[$s->id] ?? '' }}" data-sand="{{ $sandFor[$s->id] ?? '' }}" data-note="{{ $s->isAutomated() ? 'One pattern at a time: the rows below run in order.' : 'Takes up to '.$s->capacity().' pattern'.($s->capacity() > 1 ? 's' : '').' at once.' }}" @selected(old('moulding_station_id')===$s->id)>{{ $s->code }} — {{ $s->name }}</option>@endforeach</select></label>
<label>Sand system <a href="{{ route('sand-systems.index') }}" class="qd-add" data-quick="qd-sand" data-quick-target="aw-sand" data-quick-value="label">+ Add</a><select name="sand_system" id="aw-sand">@foreach($sands as $sd)<option @selected(old('sand_system')===$sd)>{{ $sd }}</option>@endforeach</select></label>
</div>
<div class="muted small" id="aw-linenote" style="margin:4px 0 10px"></div>
<div class="table-wrap"><table style="width:auto"><thead><tr><th>Pattern plate</th><th>Production order <span class="muted small" style="font-weight:400">(optional)</span></th><th>Moulds</th><th></th></tr></thead>
<tbody id="aw-rows">@foreach(old('rows', [[]]) as $k => $row)@include('work-lists._row', ['i' => $k, 'row' => $row])@endforeach</tbody></table></div>
<div style="margin:10px 0"><button type="button" class="btn" id="aw-add">+ Add row</button></div>
<label>Notes for the team <span class="muted small">(optional)</span><input name="instructions" value="{{ old('instructions') }}" style="max-width:420px"></label>
<div style="margin-top:12px"><button class="btn btn-primary">Add to list</button></div>
</form>
<template id="aw-tpl"><table><tbody>@include('work-lists._row', ['i' => '__i__', 'row' => []])</tbody></table></template>
@include('partials.search-select')
</div></div>
<script>
(function () {
    var form = document.getElementById('add-work'); if (!form) return;
    var st = document.getElementById('aw-station'), sd = document.getElementById('aw-sand'),
        body = document.getElementById('aw-rows'), tpl = document.getElementById('aw-tpl'), auto = @json($autoOrder), psand = @json($patternSand),
        next = body.querySelectorAll('.aw-row').length + 1000;
    function line() {
        var o = st.selectedOptions[0]; if (!o) return;
        if (o.dataset.sand) sd.value = o.dataset.sand;
        document.getElementById('aw-linenote').textContent = (o.dataset.team ? 'Team: ' + o.dataset.team + '. ' : 'No team is set for this line (set it in Teams & stations). ') + (o.dataset.note || '');
        sandAll();
    }
    function row(r) {
        var od = r.querySelector('.aw-order'), pt = r.querySelector('.aw-pattern'), pv = r.querySelector('.aw-preview'), prod = od.selectedOptions[0] ? od.selectedOptions[0].dataset.product : '', first = null;
        // with an order chosen, only offer the plates that have a cavity for its product (plates with no cavity map stay available)
        [].forEach.call(pt.options, function (o) {
            var list = (o.dataset.products || '').split(',').filter(Boolean), ok = !prod || !list.length || list.indexOf(prod) > -1;
            o.hidden = !ok; o.disabled = !ok; if (ok && !first) first = o;
        });
        if (pt.selectedOptions[0] && pt.selectedOptions[0].disabled && first) pt.value = first.value;
        pv.textContent = od.value ? '' : (auto[pt.value] ? 'Will link to ' + auto[pt.value] : 'No open order: a stock order is raised when the run starts.');
    }
    // A pattern is made for one sand system; on another it usually needs a different taper allowance.
    function suits(id) { var p = psand[id], s = sd.value; return !p || !p.for || p.for === s || (p.also || []).indexOf(s) > -1; }
    function sandCheck(r) {
        var pt = r.querySelector('.aw-pattern'), box = r.querySelector('.aw-sandwarn'), p = psand[pt.value];
        if (suits(pt.value)) { box.hidden = true; r.querySelector('.aw-any').checked = false; return; }
        r.querySelector('.aw-sandtext').textContent = p.code + ' was made for ' + p.for + ' and is not listed for ' + sd.value + ': it usually needs a different taper allowance.' + (p.note ? ' ' + p.note : '') + ' ';
        box.hidden = false;
    }
    function sandAll() {
        [].forEach.call(body.querySelectorAll('.aw-row'), function (r) {
            [].forEach.call(r.querySelector('.aw-pattern').options, function (o) { // mark plates that do not suit the line's sand with a warning sign
                var base = o.dataset.label || (o.dataset.label = o.textContent);
                o.textContent = suits(o.value) ? base : '⚠ ' + base;
            });
            sandCheck(r);
        });
    }
    function wire(r) {
        r.querySelector('.aw-order').addEventListener('change', function () { row(r); });
        r.querySelector('.aw-pattern').addEventListener('change', function () { row(r); sandCheck(r); });
        r.querySelector('.aw-del').addEventListener('click', function () {
            if (body.querySelectorAll('.aw-row').length > 1) r.remove();
        });
        row(r); sandAll();
    }
    function fix() { // the last remaining row cannot be deleted
        var rows = body.querySelectorAll('.aw-row');
        [].forEach.call(rows, function (r) { r.querySelector('.aw-del').disabled = rows.length < 2; });
    }
    document.getElementById('aw-add').addEventListener('click', function () {
        var html = tpl.innerHTML.replace(/__i__/g, next++), t = document.createElement('table');
        t.innerHTML = html; var r = t.querySelector('.aw-row'); body.appendChild(r); wire(r); fix();
        var last = r.querySelector('.aw-pattern'); if (last) last.focus();
    });
    body.addEventListener('click', function (e) { if (e.target.closest('.aw-del')) setTimeout(fix, 0); });
    [].forEach.call(body.querySelectorAll('.aw-row'), wire); fix();
    st.addEventListener('change', line);
    sd.addEventListener('change', sandAll);
    @if(! old('sand_system')) line(); @else (function () { var o = st.selectedOptions[0]; document.getElementById('aw-linenote').textContent = o ? (o.dataset.team ? 'Team: ' + o.dataset.team + '. ' : '') + (o.dataset.note || '') : ''; })(); @endif
})();
</script>
@if($list->status === 'DRAFT')<div class="panel"><div class="panel-header"><h2>Issue</h2></div><div class="panel-body"><form method="POST" action="{{ route('work-lists.issue', $list) }}" style="display:flex;gap:10px">@csrf<input name="issued_by" placeholder="Issued by" required><button class="btn btn-primary">Issue to teams</button></form></div></div>@endif
@unless($list->items->contains(fn($i) => $i->moulding_run_id))<form method="POST" action="{{ route('work-lists.cancel', $list) }}">@csrf<button class="btn btn-danger">Cancel list</button></form>@endunless
@endif
@include('audit._history', ['type' => 'MouldingWorkList', 'id' => $list->id])
@endsection
