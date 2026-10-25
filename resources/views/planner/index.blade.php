@extends('layouts.app')

@section('title', 'Planner')
@section('breadcrumb', 'Planner')

@section('content')
@php
    $states = ['LATE' => ['Late', 'status-cancelled'], 'AT_RISK' => ['At risk', 'status-on-hold'], 'ON_TRACK' => ['On track', 'status-completed'], 'NO_PATTERN' => ['No pattern', ''], 'NO_RATE' => ['No station rate', '']];
@endphp
<div class="page-heading">
    <div><h1>Planner</h1><p>Open production orders, earliest due first: what to mould next, when each will finish, and what is waiting downstream.</p></div>
    <div class="actions">
        <form method="GET" action="{{ route('planner.index') }}" style="display:flex;gap:8px;align-items:center">
            <label class="small muted" for="plan-day">Plan from</label><input id="plan-day" type="date" name="day" value="{{ $day->toDateString() }}">
            <button class="btn" type="submit">Show</button></form>
    </div>
</div>
@if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if ($errors->any())<div class="alert alert-error">{{ $errors->first() }}</div>@endif
@if ($unrated->isNotEmpty())
    <div class="alert alert-error">No moulds per shift on {{ $unrated->pluck('code')->implode(', ') }}; the planner leaves {{ $unrated->count() === 1 ? 'it' : 'them' }} out. Set it under <a href="{{ route('moulding-teams.index') }}">Teams &amp; stations</a>.</div>
@endif

<div class="panel">
    <div class="panel-header"><h2>Next shift</h2><span class="muted small">{{ $day->displayDate() }} · moulds already on open work lists are not suggested again</span></div>
    @if (empty($suggestion))
        <div class="empty-state">Nothing to mould: every open order is covered by open work lists, or needs a pattern or a station rate.</div>
    @else
        <div class="table-wrap"><table>
            <thead><tr><th>Station</th><th>Pattern</th><th>Order</th><th>Customer / product</th><th>Moulds</th></tr></thead>
            <tbody>
            @foreach ($suggestion as ['station' => $station, 'rows' => $rows])
                @foreach ($rows as $r)
                    <tr>
                        <td>@if ($loop->first)<strong>{{ $station->code }}</strong><div class="muted small">{{ $station->moulds_per_shift }} / shift</div>@endif</td>
                        <td>{{ $r['pattern']->pattern->name }}<div class="muted small">{{ $r['pattern']->pattern->code }} {{ $r['pattern']->shortCode() }}</div></td>
                        <td><a href="{{ route('production-orders.show', $r['order']) }}">{{ $r['order']->production_order_number }}</a></td>
                        <td>{{ $r['order']->customerLabel() }}<div class="muted small">{{ $r['order']->product?->name }}</div></td>
                        <td><strong>{{ $r['moulds'] }}</strong></td>
                    </tr>
                @endforeach
            @endforeach
            </tbody>
        </table></div>
        <div class="panel-body">
            <form method="POST" action="{{ route('planner.fill') }}" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">@csrf
                <input type="hidden" name="day" value="{{ $day->toDateString() }}">
                <label class="small" for="plan-shift">Shift</label><select id="plan-shift" name="shift">@foreach ($shifts as $s)<option>{{ $s }}</option>@endforeach</select>
                <label class="check-field small"><input type="checkbox" name="rebuild_melting" value="1" checked> Rebuild the melting plan after</label>
                <button class="btn btn-primary" type="submit">Put on a work list</button>
                <span class="muted small">Adds to the draft list for that day and shift, or starts a new one.</span>
            </form>
        </div>
    @endif
</div>

<div class="panel">
    <div class="panel-header"><h2>Orders</h2><span class="muted small">{{ count($orders) }} open</span></div>
    @if (empty($orders))
        <div class="empty-state">No released or in-progress production orders.</div>
    @else
        <div class="table-wrap"><table>
            <thead><tr><th>#</th><th>Order</th><th>Customer / product</th><th>Due</th><th>Priority</th><th>Moulds left</th><th>On lists</th><th>Pattern / station</th><th>Poured, not finished</th><th>Projected finish</th><th></th></tr></thead>
            <tbody>
            @foreach ($orders as $o)
                <tr>
                    <td class="muted">{{ $loop->iteration }}</td>
                    <td><a href="{{ route('production-orders.show', $o['order']) }}">{{ $o['order']->production_order_number }}</a><div class="muted small">{{ $o['order']->status }}</div></td>
                    <td>{{ $o['order']->customerLabel() }}<div class="muted small">{{ $o['order']->product?->name }}</div></td>
                    <td>{{ $o['due']?->displayDate() ?? '—' }}</td>
                    <td>{{ $o['order']->priority }}</td>
                    <td>{{ $o['moulds_left'] ?: '—' }}</td>
                    <td>{{ $o['on_lists'] ?: '—' }}</td>
                    <td>{{ $o['pattern']?->pattern->code ?? '—' }}<div class="muted small">{{ $o['station']?->code }}</div></td>
                    <td>@forelse ($o['backlog'] as $where => $pieces)<div class="small">{{ $where }}: <strong>{{ $pieces }}</strong></div>@empty<span class="muted">—</span>@endforelse</td>
                    <td>{{ $o['finish']?->displayDate() ?? '—' }}@if ($o['note'])<div class="muted small">{{ $o['note'] }}</div>@elseif (! $o['finish'] && $o['on_lists'])<div class="muted small">All on work lists.</div>@endif</td>
                    <td>@if ($o['finish'] || $o['state'] !== 'ON_TRACK')<span class="status {{ $states[$o['state']][1] }}">{{ $states[$o['state']][0] }}</span>@endif</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    @endif
</div>

<div class="panel">
    <div class="panel-header"><h2>Assumptions</h2></div>
    <div class="panel-body">
        <form method="POST" action="{{ route('planner.rules') }}" class="form-grid">@csrf @method('PUT')
            @foreach ($rules as $key => $r)
                <label>{{ $r['label'] }}<input type="number" name="{{ $key }}" value="{{ $r['value'] }}" min="{{ $r['min'] }}" max="{{ $r['max'] }}" required></label>
            @endforeach
            <div><button class="btn" type="submit">Save</button></div>
        </form>
        <p class="muted small">Projected finish = the day the order's last moulds are made, plus the days from moulding to finished. Stations are loaded up to their moulds per shift, after the moulds already on work lists from this day on; a pattern goes to its preferred station when that has a rate.</p>
    </div>
</div>
@endsection
