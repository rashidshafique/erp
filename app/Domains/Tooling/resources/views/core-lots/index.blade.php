@extends('layouts.app')

@section('title', 'Core stock')
@section('breadcrumb')<a href="{{ route('cores.index') }}">Cores</a> / Core stock @endsection

@section('content')
@include('partials.list-ui')
<style>
    .cs-tiles { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 12px; padding: 14px 20px; border-top: 1px solid #e6e7e9; }
    .cs-tile { display: block; padding: 10px 12px; border: 1px solid #e6e7e9; border-radius: 8px; color: #1c1d1f; text-decoration: none !important; }
    .cs-tile.on { border-color: var(--accent); box-shadow: inset 0 0 0 1px var(--accent); }
    .cs-tile .t { font-size: 12.5px; color: #55585d; } .cs-tile .n { font-size: 22px; font-weight: 600; font-variant-numeric: tabular-nums; } .cs-tile .n.zero { color: #9a9da2; }
    .cs-sub { color: #6b6e73; font-size: 12.5px; font-weight: 400; margin-left: 8px; }
    .cs-card table td.r, .cs-card table th.r { text-align: right; font-variant-numeric: tabular-nums; }
    .cs-empty { padding: 36px 20px; text-align: center; border-top: 1px solid #e6e7e9; } .cs-empty p { color: #6b6e73; max-width: 460px; margin: 6px auto 14px; }
</style>
@php
    $statusChip = ['MADE' => 'status-in-progress', 'STORED' => 'status-completed', 'USED_UP' => 'status-cancelled'];
    $qcChip = ['PENDING' => 'status-on-hold', 'PASSED' => 'status-completed', 'FAILED' => 'status-cancelled', 'NOT_REQUIRED' => ''];
    $core = request('core');
@endphp
<div class="ls-card cs-card">
    <div class="ls-head">
        <h1 class="ls-title">Core stock @if ($anyLots)<span class="cs-sub">{{ $openLots }} {{ $openLots === 1 ? 'lot' : 'lots' }} with cores left</span>@endif</h1>
        <div class="ls-actions">
            @if (\App\Support\SettingsNav::canOpen())<a class="ls-btn" href="{{ route('settings.rules', ['advanced' => 1]).'#cores' }}">Core rules</a>@endif
            <a class="ls-btn primary" href="{{ route('core-lots.create', array_filter(['core' => $core])) }}">+ Cores made</a>
        </div>
    </div>
    @if (! $anyLots)
        <div class="cs-empty">
            <strong>No core lots yet</strong>
            <p>Record cores as they are made and this page tracks what is on hand, where it is kept and what each moulding run used.</p>
            <a class="ls-btn primary" href="{{ route('core-lots.create') }}">+ Cores made</a>
        </div>
    @else
        <div class="cs-tiles">
            @foreach ($stocked as $c)
                @php $n = $onHand[$c->id] ?? 0; @endphp
                <a @class(['cs-tile', 'on' => $core === $c->id]) href="{{ route('core-lots.index', array_filter(['core' => $core === $c->id ? null : $c->id, 'show' => $all ? 'all' : null])) }}">
                    <div class="t">{{ $c->name }}</div><div @class(['n', 'zero' => $n === 0])>{{ \App\Support\Formats::number($n) }}</div>
                </a>
            @endforeach
        </div>
        <nav class="ls-tabs" aria-label="Lots shown">
            <a href="{{ route('core-lots.index', array_filter(['core' => $core])) }}" @class(['on' => ! $all])>Lots with cores left</a>
            <a href="{{ route('core-lots.index', array_filter(['core' => $core, 'show' => 'all'])) }}" @class(['on' => $all])>All lots</a>
        </nav>
        <form class="ls-tools" method="GET" action="{{ route('core-lots.index') }}">
            @if ($all)<input type="hidden" name="show" value="all">@endif
            <label class="muted small" for="core">Core</label>
            <select id="core" name="core" class="ls-tool" onchange="this.form.submit()"><option value="">All</option>@foreach ($cores as $c)<option value="{{ $c->id }}" @selected($core === $c->id)>{{ $c->name }}</option>@endforeach</select>
            <noscript><button class="ls-tool" type="submit">Show</button></noscript>
        </form>
        @if ($lots->isEmpty())
            <p class="ls-empty">{{ $all ? 'No lots for this core.' : 'No lots with cores left.' }}</p>
        @else
            <div class="ls-scroll"><table>
                <thead><tr><th>Lot</th><th>Core</th><th>Box</th><th>Made</th><th class="r">Left / made</th><th>Where</th>@if ($showCheck)<th>Check</th>@endif<th>Status</th></tr></thead>
                <tbody>
                @foreach ($lots as $lot)
                    <tr>
                        <td><a href="{{ route('core-lots.show', $lot) }}" class="mono">{{ $lot->lot_number }}</a></td>
                        <td>{{ $lot->coreDefinition->name }}</td>
                        <td class="mono">{{ $lot->coreBox->code }}</td>
                        <td>{{ $lot->made_on->displayDate() }}</td>
                        <td class="r">{{ $lot->quantity_made - (int) $lot->taken }} / {{ $lot->quantity_made }}</td>
                        <td>{{ $lot->location ?: '—' }}</td>
                        @if ($showCheck)<td>@if ($lot->qc_status === 'NOT_REQUIRED')<span class="muted">Not required</span>@else<span class="status {{ $qcChip[$lot->qc_status] ?? '' }}">{{ \App\Models\CoreLot::QC[$lot->qc_status] }}</span>@endif</td>@endif
                        <td><span class="status {{ $statusChip[$lot->status] ?? '' }}">{{ \App\Models\CoreLot::STATUSES[$lot->status] ?? $lot->status }}</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            @if ($lots->hasPages())<div class="pagination" style="padding:8px 20px"><div class="muted small">Showing {{ $lots->firstItem() }}–{{ $lots->lastItem() }} of {{ $lots->total() }}</div><div class="pagination-links">{{ $lots->links() }}</div></div>@endif
        @endif
    @endif
</div>
@endsection
