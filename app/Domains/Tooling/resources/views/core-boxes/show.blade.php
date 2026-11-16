@extends('layouts.app')

@section('title', $box->code)
@section('breadcrumb')<a href="{{ route('core-boxes.index') }}">Core boxes</a> / {{ $box->code }} @endsection

@section('content')
<div class="page-heading">
    <div><h1>{{ $box->name }}</h1><p>Core box · <span class="mono">{{ $box->code }}</span> · revision {{ $box->revision }} · @php $shown = $box->displayStatus(); @endphp<span class="status {{ match ($shown) { 'ACTIVE' => 'status-completed', 'LENT' => 'status-in-progress', default => 'status-cancelled' } }}">{{ \App\Models\CoreBox::STATUS_LABELS[$shown] ?? $shown }}</span>@if ($shown === 'LENT' && $box->custody_location) <span class="muted">with {{ $box->custody_location }}</span>@endif</p></div>
    <div class="actions">
        @if ($box->trashed())
            <form method="POST" action="{{ route('core-boxes.restore', $box) }}" style="display:inline">@csrf<button class="btn btn-primary" type="submit">Restore core box</button></form>
        @else
            <a class="btn" href="{{ route('core-boxes.edit', $box) }}">Edit</a>
            @if (Route::has('developments.create'))<a class="btn" href="{{ route('developments.create', ['from' => 'core_box:'.$box->id]) }}">Start replacement</a>@endif
            @if (Route::has('tooling-maintenance.create'))<a class="btn" href="{{ route('tooling-maintenance.create', ['tooling_type' => 'CORE_BOX', 'core_box_id' => $box->id, 'activity_type' => 'REPAIR']) }}">Raise refurb request</a>@endif
            <form method="POST" action="{{ route('core-boxes.archive', $box) }}" style="display:inline" onsubmit="return confirm(@js('Archive '.$box->code.'? It leaves the lists and pickers and can be restored.'))">@csrf @method('DELETE')<button class="btn" type="submit">Archive</button></form>
        @endif
    </div>
</div>
@php
    $made = Route::has('developments.index') ? \App\Domains\Development\Models\PatternDevelopment::touching($box)->first(fn ($d) => $d->tools->contains(fn ($t) => $t->core_box_id === $box->id && $t->route === 'NEW')) : null;
    $used = $life ? app(\App\Domains\Tooling\Services\ToolingLifeStatusService::class)->calculate($life) : null;
    $unit = $life ? strtolower(str_replace('_', ' ', $life->usage_unit)) : '';
    $lifeLine = $used ? \App\Support\Formats::number($used['current_usage'], 0).' of '.\App\Support\Formats::number((float) $life->service_life_quantity, 0).' '.$unit : null;
    $line = array_filter([
        $made ? 'Built on '.$made->code : null,
        $made && $made->isOpen() && $box->status === 'ACTIVE' && $made->tools->contains(fn ($t) => $t->core_box_id === $box->id && in_array($t->status, ['BUILT', 'TRIAL_PASSED'], true)) ? 'Trial cores allowed' : null,
        $box->commissioned_on ? 'in use since '.$box->commissioned_on->displayDate() : null,
        $lifeLine,
    ]);
    $editable = ! $box->trashed();
@endphp
@if ($line)<p class="muted" style="margin:0 0 12px">{{ implode(' · ', $line) }}</p>@endif
@if ($box->trashed())<div class="alert alert-warning" style="display:flex;align-items:center;gap:10px"><span class="status status-cancelled">Archived</span><span>Archived {{ $box->deleted_at->displayDate() }} — hidden from pickers and lists. Facts stay readable.</span><form method="POST" action="{{ route('core-boxes.restore', $box) }}" style="margin-left:auto">@csrf<button class="btn" type="submit">Restore</button></form></div>@endif
<style>
    .core-top { display: flex; gap: 20px; align-items: flex-start; } .core-top .cover-box { float: none; margin: 0; flex: none; } .core-top > div { flex: 1; min-width: 0; }
    .core-top .tool-facts, .cb-facts4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    .cb-own-edit:has(> details:not([open])) { display: none; }
    .cb-life { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 12px; }
    .cb-life .bar { flex: 0 1 320px; height: 8px; border-radius: 4px; background: color-mix(in srgb, var(--border) 70%, transparent); overflow: hidden; }
    .cb-life .bar span { display: block; height: 100%; background: var(--accent, #2f5fd0); }
    .cb-life .bar.warn span { background: #b8730a; } .cb-life .bar.over span { background: #b3261e; }
    @media (max-width: 800px) { .core-top { flex-direction: column; } .core-top .tool-facts, .cb-facts4 { grid-template-columns: 1fr 1fr; } }
</style>
<div class="tab-marker" data-tab-start="Core box"></div>
<div class="panel"><div class="panel-body">
    <div class="core-top">
        @include('attachments._cover', ['type' => 'core_box', 'id' => $box->id])
        <div>
            <dl class="tool-facts">
                <div><dt>Material</dt><dd>{{ $box->material ?: '—' }}</dd></div>
                <div><dt>Cavities</dt><dd>{{ $box->cavities }}@if ($box->blockedCavities() > 0) · {{ $box->blockedCavities() }} blocked @endif</dd></div>
                <div><dt>Core weight</dt><dd>{{ $box->nominal_core_weight_kg !== null ? rtrim(rtrim($box->nominal_core_weight_kg, '0'), '.').' kg' : '—' }}</dd></div>
                <div><dt>Commissioned</dt><dd>{{ $box->commissioned_on?->displayDate() ?? '—' }}</dd></div>
                <div><dt>Mark code</dt><dd>@include('tooling::tools._mark', ['tool' => $box, 'type' => 'core_box', 'bare' => true])</dd></div>
                <div><dt>Barcode</dt><dd>{!! \App\Support\Code128::svg($box->code, 44, 2) !!}</dd></div>
                <div><dt>Where it comes from</dt><dd>
                    @if ($box->modifiedFromBox)Modified from <a href="{{ route('core-boxes.show', $box->modifiedFromBox) }}"><span class="mono">{{ $box->modifiedFromBox->code }}</span> {{ $box->modifiedFromBox->name }}</a>
                    @elseif ($box->modifiedFromPattern)Modified from <a href="{{ route('patterns.show', $box->modifiedFromPattern) }}"><span class="mono">{{ $box->modifiedFromPattern->code }}</span> {{ $box->modifiedFromPattern->name }}</a>
                    @else{{ \App\Models\CoreBox::ORIGINS[$box->origin] ?? $box->origin }}@endif</dd></div>
                <div><dt>Modified into</dt><dd>@forelse ($derived as $d)<a href="{{ route('core-boxes.show', $d) }}"><span class="mono">{{ $d->code }}</span> {{ $d->name }}</a>@if (! $loop->last), @endif @empty — @endforelse</dd></div>
            </dl>
            @if ($box->description || $box->notes)
            <div class="core-text" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;padding-top:14px;margin-top:14px;border-top:1px solid var(--border)">
                <div><div class="muted small">Description</div><div>{{ $box->description ?: '—' }}</div></div>
                <div><div class="muted small">Notes</div><div>{{ $box->notes ?: '—' }}</div></div>
            </div>
            @endif
        </div>
    </div>
</div></div>

@include('tooling::core-boxes._ownership')

<div class="panel" id="cavity-map"><div class="panel-header"><h2>Cavity map</h2>@if ($editable)<a class="btn btn-small" href="{{ route('core-boxes.edit', $box) }}">Edit cavities</a>@endif</div>
    <div class="panel-body">@include('tooling::core-boxes._cavity-map', ['editable' => $editable, 'cores' => $cores])</div>
</div>

<div class="panel"><div class="panel-header"><h2>Life &amp; maintenance</h2><span class="muted small"><a href="{{ route('tooling-life.dashboard') }}">Life limits</a> · <a href="{{ route('tooling-maintenance.index') }}">Maintenance register</a></span></div>
    <div class="panel-body">
        @if ($used)
            @php $pct = $used['life_percent'] !== null ? (float) $used['life_percent'] : null; @endphp
            <div class="cb-life">
                @if ($pct !== null)<div class="bar {{ $pct >= 100 ? 'over' : ($pct >= 80 ? 'warn' : '') }}" role="img" aria-label="{{ round($pct) }}% of the box's life used"><span style="width:{{ min(100, max(0, $pct)) }}%"></span></div>@endif
                <span><strong>{{ $lifeLine }}</strong>@if ($pct !== null) · {{ round($pct) }}%@endif</span>
                <span class="muted small">counted from cores made</span>
            </div>
        @else<p class="muted small" style="margin:0 0 10px">No life limit · <a href="{{ route('tooling-life.create') }}">set one</a> to see wear and get a replacement warning.</p>@endif
    </div>
    <div class="table-wrap"><table><thead><tr><th>Record</th><th>Activity</th><th>Status</th><th>Reported</th></tr></thead><tbody>
    @forelse ($records as $r)<tr><td><a href="{{ route('tooling-maintenance.show', $r) }}">{{ $r->record_number }}</a></td><td>{{ str_replace('_', ' ', $r->activity_type) }}</td><td>{{ $r->status }}</td><td>{{ $r->reported_at?->displayDate() }}</td></tr>
    @empty<tr><td colspan="4" class="muted">No maintenance recorded.</td></tr>@endforelse</tbody></table></div>
</div>
@if (Route::has('developments.index'))@include('development::_register_block', ['record' => $box])@endif
<div class="tab-marker" data-tab-start="Files & history"></div>
@include('attachments._panel', ['type' => 'core_box', 'id' => $box->id])
@include('audit._history', ['type' => 'CoreBox', 'id' => $box->id])
@endsection
