@extends('layouts.app')
@section('title', 'Production queue')
@section('content')
<style>
    [x-cloak] { display: none !important; }
    .qflow { display: flex; align-items: stretch; gap: 6px; overflow-x: auto; padding-bottom: 4px; margin-bottom: 18px; }
    .qstep { flex: 1 0 128px; display: block; padding: 12px 14px; background: var(--surface); border: 1px solid var(--border); border-top: 4px solid var(--success); border-radius: 10px; color: inherit; text-decoration: none; }
    a.qstep:hover { text-decoration: none; border-color: var(--accent); }
    .qstep.amber { border-top-color: var(--warning); }
    .qstep.red { border-top-color: var(--danger); }
    .qstep.empty { border-top-color: var(--border); opacity: .55; }
    .qstep-name { color: var(--muted); font-size: 12px; font-weight: 600; line-height: 1.3; }
    .qstep-lots { margin-top: 6px; font-size: 24px; font-weight: 750; line-height: 1; }
    .qstep-lots small { font-size: 12px; font-weight: 600; color: var(--muted); }
    .qstep-note { margin-top: 4px; color: var(--muted); font-size: 11.5px; }
    .qstep.red .qstep-age { color: var(--danger); font-weight: 650; }
    .qstep.amber .qstep-age { color: var(--warning); font-weight: 650; }
    .qarrow { align-self: center; color: var(--muted); flex: 0 0 auto; }
    .btn-group { display: inline-flex; }
    .btn-group .btn { border-radius: 0; }
    .btn-group .btn:first-child { border-radius: 7px 0 0 7px; }
    .btn-group .btn:last-child { border-radius: 0 7px 7px 0; margin-left: -1px; }
    .qmould td { background: #fafbfc; font-size: 12.5px; }
    .qmould-toggle { border: 1px solid var(--border); background: var(--surface); border-radius: 999px; padding: 1px 8px; font: inherit; font-size: 11.5px; cursor: pointer; margin-top: 3px; }
</style>

<div class="page-heading"><div><h1>Production queue</h1><p>How much work sits at each step after the pour. Oldest first within each step.</p></div><div class="actions"><span class="btn-group" role="group" aria-label="Queue layout"><a class="btn {{ $view === 'list' ? 'btn-primary' : '' }}" href="{{ route('lots.queue') }}" @if ($view === 'list') aria-current="page" @endif>List</a><a class="btn {{ $view === 'board' ? 'btn-primary' : '' }}" href="{{ route('lots.queue', ['view' => 'board']) }}" @if ($view === 'board') aria-current="page" @endif>Board</a></span>@if (Route::has('finished-goods.index'))<a class="btn" href="{{ route('finished-goods.index') }}">Finished goods ({{ $awaitingDispatch }} packed lines)</a>@endif</div></div>

@if ($openLots)
<nav class="qflow" aria-label="Work at each step">
    @foreach ($steps as $step)
        @if (! $loop->first)<span class="qarrow" aria-hidden="true">→</span>@endif
        @php $tag = $step['lot_count'] ? 'a' : 'div'; @endphp
        <{{ $tag }} class="qstep {{ $step['tone'] }}" @if ($step['lot_count']) href="#step-{{ $step['key'] }}" @click="window.dispatchEvent(new CustomEvent('queue-open', { detail: '{{ $step['key'] }}' }))" @endif>
            <div class="qstep-name">{{ $step['label'] }}</div>
            <div class="qstep-lots">{{ $step['lot_count'] }} <small>{{ $step['lot_count'] === 1 ? 'lot' : 'lots' }}</small></div>
            @if ($step['lot_count'])
                <div class="qstep-note">{{ \App\Support\Formats::number($step['pieces']) }} pcs</div>
                @if ($step['oldest_h'] !== null)<div class="qstep-note qstep-age">oldest {{ $step['oldest_h'] >= 72 ? intdiv($step['oldest_h'], 24).' d' : $step['oldest_h'].' h' }}</div>@endif
            @endif
        </{{ $tag }}>
    @endforeach
</nav>
@endif

<form method="POST" action="{{ route('lots.bulk-stage') }}">@csrf
@if ($openLots)
<div class="panel"><div class="panel-body">
<strong>Move the ticked lots together</strong>
<div style="display:flex;gap:14px;flex-wrap:wrap;margin:8px 0">@foreach($stages as $st)<label><input type="checkbox" name="stages[]" value="{{ $st->id }}"> {{ $st->name }}</label>@endforeach</div>
<div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
<input type="number" min="0" name="rejected" placeholder="Rejected (total)" style="width:150px" value="{{ old('rejected') }}">
<input name="rejection_reason" placeholder="Rejection reason" maxlength="100" value="{{ old('rejection_reason') }}">
<input name="operator_name" placeholder="Operator" maxlength="100" value="{{ old('operator_name') }}">
<input name="cooling_override_reason" placeholder="Reason, to shake out before cooling is met" maxlength="300">
<button class="btn btn-primary" type="submit" onclick="return confirm('Record the chosen stages for the ticked lots?')">Move ticked lots</button></div>
<p class="muted small" style="margin:8px 0 0">Every ticked lot passes the stages in order with all its pieces. Rejected pieces are taken from the oldest lots first.</p>
</div></div>
@endif

@if ($view === 'board' && $openLots)
@include('lots._queue-board')
@else
@forelse (array_filter($steps, fn ($s) => $s['lot_count'] > 0) as $b)
@php
    $isReady = $b['key'] === 'ready';
    $multi = collect($b['lots'])->contains(fn ($r) => count($r['moulds']) > 1);
@endphp
<div class="panel" id="step-{{ $b['key'] }}" x-data="{ open: {{ $isReady ? 'false' : 'true' }}, all: false }" @queue-open.window="if ($event.detail === '{{ $b['key'] }}') open = true">
    <div class="panel-header">
        <h2>{{ $b['label'] }}</h2>
        <span class="muted small">{{ $b['lot_count'] }} {{ $b['lot_count'] === 1 ? 'lot' : 'lots' }} · {{ \App\Support\Formats::number($b['pieces']) }} pcs</span>
        <span style="margin-left:auto;display:flex;gap:6px">
            @if ($multi)
                <button type="button" class="btn btn-small" x-show="open" @click="window.dispatchEvent(new CustomEvent('queue-moulds', { detail: { step: '{{ $b['key'] }}', open: true } }))">Expand all</button>
                <button type="button" class="btn btn-small" x-show="open" @click="window.dispatchEvent(new CustomEvent('queue-moulds', { detail: { step: '{{ $b['key'] }}', open: false } }))">Collapse all</button>
            @endif
            @if ($isReady)<button type="button" class="btn btn-small" @click="open = !open" x-text="open ? 'Hide lots' : 'Show lots'">Show lots</button>@endif
        </span>
    </div>
    <div class="table-wrap" x-show="open" @if ($isReady) x-cloak @endif><table>
        <thead><tr><th><input type="checkbox" class="bucket-all" aria-label="Select all in this step"></th><th>Lot</th><th>Product</th><th>Order</th><th>Pieces</th><th>Poured</th><th>Age</th><th>Cooling</th><th>Status</th></tr></thead>
        @foreach ($b['lots'] as $r)
        @php $l = $r['lot']; $moulds = count($r['moulds']); @endphp
        <tbody x-data="{ moulds: false }" @queue-moulds.window="if ($event.detail.step === '{{ $b['key'] }}') moulds = $event.detail.open" @if ($isReady && $loop->index >= 15) x-show="all" x-cloak @endif>
            <tr>
                <td>@if($l->status === 'IN_PRODUCTION')<input type="checkbox" name="lots[]" value="{{ $l->id }}" class="queue-lot" aria-label="Select {{ $l->label }}">@endif</td>
                <td><a href="{{ route('lots.show', $l) }}">{{ $l->label }}</a>
                    @if ($moulds > 1)<div><button type="button" class="qmould-toggle" @click="moulds = !moulds" :aria-expanded="moulds.toString()"><span x-text="moulds ? '▾' : '▸'">▸</span> {{ $moulds }} moulds</button></div>@endif
                </td>
                <td><x-product-cell :product="$l->product" /></td>
                <td>{{ $l->productionOrder?->production_order_number }}@if($r['urgent'])<div class="muted small" @if ($r['urgent']->lt(today())) style="color:var(--danger)" @endif>required {{ $r['urgent']->displayShortDate() }}</div>@endif</td>
                <td>{{ $r['pieces'] }}</td>
                <td>{{ $r['poured']?->displayShortDateTime() ?? '—' }}</td>
                <td>{{ $r['age_h'] !== null ? $r['age_h'].' h' : '—' }}</td>
                <td>@if($r['cool'])@if($r['cool']['wait'] > 0)<span class="status status-in-progress">wait {{ $r['cool']['wait'] }} min</span>@else <span class="muted small">met</span>@endif @endif</td>
                <td>{{ $l->status }}</td>
            </tr>
            @if ($moulds > 1)
                @foreach ($r['moulds'] as $m)
                    <tr class="qmould" x-show="moulds" x-cloak><td></td><td class="mono" style="padding-left:22px">↳ {{ $m['number'] }}</td><td colspan="7" class="muted">{{ $m['pieces'] }} {{ $m['pieces'] === 1 ? 'piece' : 'pieces' }} from this mould</td></tr>
                @endforeach
            @endif
        </tbody>
        @endforeach
    </table>
    @if ($isReady && $b['lot_count'] > 15)
        <div class="panel-body" x-show="!all"><button type="button" class="btn btn-small" @click="all = true">Show all {{ $b['lot_count'] }} lots</button> <span class="muted small">Oldest 15 shown.</span></div>
    @endif
    </div>
</div>
@empty<div class="panel"><div class="panel-body muted">No open lots.</div></div>@endforelse
@endif
</form>
<script>
document.querySelectorAll('.bucket-all').forEach(h => h.addEventListener('change', e => {
    // Only the lots on show: hidden ones (past the first 15 of a long step) are never ticked unseen.
    e.target.closest('table, .qcol').querySelectorAll('.queue-lot').forEach(b => { if (b.offsetParent !== null) b.checked = e.target.checked; });
}));
</script>
@endsection
