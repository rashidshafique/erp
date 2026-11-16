@extends('layouts.app')

@section('title', $lot->lot_number)
@section('breadcrumb')@if (\App\Domains\Tooling\Support\CoreRules::stockOn())<a href="{{ route('core-lots.index') }}">Core stock</a>@else<a href="{{ route('cores.show', $lot->coreDefinition) }}">{{ $lot->coreDefinition->code }}</a>@endif / {{ $lot->lot_number }} @endsection

@section('content')
@php
    $statusChip = ['MADE' => 'status-in-progress', 'STORED' => 'status-completed', 'USED_UP' => 'status-cancelled'];
    $used = $lot->quantity_made - $remaining;
    $left = $lot->quantity_made;
    $rows = $lot->uses->map(function ($use) use (&$left) { $left -= $use->quantity; return ['use' => $use, 'left' => $left]; })->reverse();
@endphp
<style>
    .cl-facts { grid-template-columns: repeat(4, minmax(0, 1fr)); } @media (max-width: 800px) { .cl-facts { grid-template-columns: 1fr 1fr; } }
    .cl-check { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; padding: 12px 14px; margin-bottom: 16px; border: 1px solid #f0d9a8; border-radius: 8px; background: #fffaf0; color: #5d3d05; }
    .cl-check .acts { margin-left: auto; display: flex; gap: 8px; }
    .cl-check.failed { border-color: #f1c4bf; background: #fdf2f1; color: #8a2a22; }
</style>
<div class="page-heading">
    <div><h1><span class="mono">{{ $lot->lot_number }}</span> <span class="status {{ $statusChip[$lot->status] ?? '' }}" style="vertical-align:middle">{{ \App\Models\CoreLot::STATUSES[$lot->status] ?? $lot->status }}</span></h1></div>
    <div class="actions">
        @if ($remaining > 0)
            <button type="button" class="btn" data-quick="qd-lot-move">Move</button>
            @if ($lot->isIssuable())<button type="button" class="btn" data-quick="qd-lot-use">Use</button>@endif
            <form method="POST" action="{{ route('core-lots.take', $lot) }}" style="display:inline" onsubmit="return confirm(@js('Scrap the '.$remaining.' cores left in '.$lot->lot_number.'?'))">@csrf
                <input type="hidden" name="kind" value="SCRAPPED"><input type="hidden" name="quantity" value="{{ $remaining }}"><input type="hidden" name="used_on" value="{{ today()->format('Y-m-d') }}">
                <button class="btn" type="submit">Scrap remainder</button></form>
        @endif
        <a class="btn" href="{{ route('core-lots.create', ['core' => $lot->core_definition_id]) }}">+ Cores made</a>
    </div>
</div>

@if ($lot->qc_status === 'PENDING')
    <div class="cl-check" role="status">
        <span><strong>Check required.</strong> These cores cannot be used until the lot passes. Fail it to scrap them or raise an NCR.</span>
        <span class="acts">
            <form method="POST" action="{{ route('core-lots.check', $lot) }}">@csrf @method('PUT')<input type="hidden" name="result" value="PASSED"><button class="btn btn-primary" type="submit">Pass</button></form>
            <form method="POST" action="{{ route('core-lots.check', $lot) }}">@csrf @method('PUT')<input type="hidden" name="result" value="FAILED"><button class="btn" type="submit">Fail</button></form>
        </span>
    </div>
@elseif ($lot->qc_status === 'FAILED')
    <div class="cl-check failed" role="status">
        <span><strong>Failed its check.</strong> The cores cannot be used; scrap what is left.@if ($lot->qc_notes) {{ $lot->qc_notes }}@endif</span>
        <span class="acts"><form method="POST" action="{{ route('core-lots.check', $lot) }}">@csrf @method('PUT')<input type="hidden" name="result" value="PASSED"><button class="btn" type="submit">Pass after all</button></form></span>
    </div>
@endif

<div class="panel"><div class="panel-body">
    <dl class="tool-facts cl-facts">
        <div><dt>Core</dt><dd><x-item :name="$lot->coreDefinition->name" :code="$lot->coreDefinition->code" :href="route('cores.show', $lot->coreDefinition)" /></dd></div>
        <div><dt>Core box</dt><dd><a href="{{ route('core-boxes.show', $lot->coreBox) }}" class="mono">{{ $lot->coreBox->code }}</a> <span class="muted">Rev {{ $lot->coreBox->revision }}</span></dd></div>
        <div><dt>Made on</dt><dd>{{ $lot->made_on->displayDate() }}</dd></div>
        <div><dt>Where</dt><dd>{{ $lot->location ?: '—' }}</dd></div>
        <div><dt>Made</dt><dd>{{ $lot->quantity_made }}</dd></div>
        <div><dt>Used</dt><dd>{{ $used }}</dd></div>
        <div><dt>Left</dt><dd>{{ $remaining }}</dd></div>
        <div><dt>Status</dt><dd><span class="status {{ $statusChip[$lot->status] ?? '' }}">{{ \App\Models\CoreLot::STATUSES[$lot->status] ?? $lot->status }}</span>@if ($lot->qc_status === 'PASSED')<span class="sub">Check passed</span>@endif</dd></div>
    </dl>
    @if ($lot->notes)<p class="muted" style="margin:12px 0 0">{{ $lot->notes }}</p>@endif
</div></div>

<div class="panel"><div class="panel-header"><h2>Uses</h2></div><div class="table-wrap"><table>
    <thead><tr><th>Date</th><th>Used in</th><th class="r">Quantity</th><th class="r">Left after</th></tr></thead>
    <tbody>
    @foreach ($rows as $row)
        @php $use = $row['use']; @endphp
        <tr>
            <td>{{ $use->used_on->displayDate() }}</td>
            <td>@if ($use->kind === 'SCRAPPED') Scrapped @elseif ($use->kind === 'CORRECTION') Corrected entry @elseif ($use->mouldingRun)<a href="{{ route('moulding-runs.show', $use->mouldingRun) }}" class="mono">{{ $use->mouldingRun->run_number }}</a> <span class="muted">moulding run @if ($use->mouldingRun->patternRevision?->pattern) · {{ $use->mouldingRun->patternRevision->pattern->name }}@endif</span>@else Used in moulding @endif
                @if ($use->notes)<div class="muted small">{{ $use->notes }}</div>@endif</td>
            <td class="r">{{ $use->quantity }}</td>
            <td class="r">{{ $row['left'] }}</td>
        </tr>
    @endforeach
        <tr><td>{{ $lot->made_on->displayDate() }}</td><td>Made in <a href="{{ route('core-boxes.show', $lot->coreBox) }}" class="mono">{{ $lot->coreBox->code }}</a></td><td class="r">+{{ $lot->quantity_made }}</td><td class="r">{{ $lot->quantity_made }}</td></tr>
    </tbody>
</table></div></div>

@if ($remaining > 0)
    @include('partials.quick-dialog', ['id' => 'qd-lot-move', 'title' => 'Move '.$lot->lot_number, 'action' => route('core-lots.location', $lot), 'submit' => 'Move', 'fields' => 'tooling::core-lots._move-fields'])
    @if ($lot->isIssuable())@include('partials.quick-dialog', ['id' => 'qd-lot-use', 'title' => 'Use cores from '.$lot->lot_number, 'action' => route('core-lots.take', $lot), 'submit' => 'Record use', 'fields' => 'tooling::core-lots._use-fields'])@endif
@endif
@endsection
