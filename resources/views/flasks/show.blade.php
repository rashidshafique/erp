@extends('layouts.app')

@section('title', $flask->code)
@section('breadcrumb', 'Flasks / '.$flask->code)

@section('content')
@php
    $statusChip = ['AVAILABLE' => 'status-completed', 'IN_MOULD' => 'status-in-progress', 'REPAIR' => 'status-on-hold', 'RETIRED' => 'status-cancelled', 'LOST' => 'status-cancelled'];
    $lifePct = $life?->service_life_quantity ? min(100, round(100 * $flask->cycles / (float) $life->service_life_quantity)) : null;
@endphp
<div class="page-heading">
    <div>
        <h1 class="mono">{{ $flask->code }}</h1>
        <p>{{ $flask->size?->name }} · {{ \App\Models\Flask::POSITIONS[$flask->position] ?? $flask->position }} · <span class="status {{ $statusChip[$flask->status] ?? '' }}">{{ \App\Models\Flask::STATUSES[$flask->status] ?? $flask->status }}</span></p>
    </div>
    <div class="actions">
        <a class="btn" href="{{ route('flasks.index') }}">All flasks</a>
        <a class="btn" href="{{ route('labels.show', ['flask', $flask->id]) }}" target="_blank">Print label</a>
        <a class="btn btn-primary" href="{{ route('flasks.edit', $flask) }}">Edit flask</a>
    </div>
</div>

<div class="tab-marker" data-tab-start="Flask"></div>
<div class="panel">
    <div class="panel-header"><h2>Flask</h2></div>
    <div class="panel-body" style="display:flow-root">
        @include('attachments._cover', ['type' => 'flask', 'id' => $flask->id, 'size' => 150])
        <div class="detail-grid">
            <div><div class="detail-label">Stamped mark</div><div class="detail-value mono" style="font-size:20px;letter-spacing:2px">{{ $flask->mark_code ?? '—' }}</div></div>
            <div><div class="detail-label">Size</div><div class="detail-value">{{ $flask->size?->name }}<div class="muted small">{{ $flask->size?->dimensions() }}</div></div></div>
            <div><div class="detail-label">Position</div><div class="detail-value">{{ \App\Models\Flask::POSITIONS[$flask->position] ?? $flask->position }}</div></div>
            <div><div class="detail-label">Construction</div><div class="detail-value">{{ \App\Models\Flask::CONSTRUCTIONS[$flask->construction] ?? '—' }}</div></div>
            <div><div class="detail-label">Material · weight</div><div class="detail-value">{{ $flask->material ?: '—' }} · {{ $flask->weight_kg !== null ? rtrim(rtrim(\App\Support\Formats::number((float) $flask->weight_kg, 2), '0'), '.').' kg' : '—' }}</div></div>
            <div><div class="detail-label">Location</div><div class="detail-value">{{ $flask->location ?: '—' }}</div></div>
            <div><div class="detail-label">Fixed partner</div><div class="detail-value">@if ($flask->partner)<a class="mono" href="{{ route('flasks.show', $flask->partner) }}">{{ $flask->partner->label() }}</a>@else — @endif</div></div>
            <div><div class="detail-label">Uses</div><div class="detail-value mono">{{ \App\Support\Formats::number($flask->cycles) }}@if ($life?->service_life_quantity) <span class="muted small">of {{ \App\Support\Formats::number((float) $life->service_life_quantity) }} ({{ $lifePct }} %)</span>@endif</div>
                <div class="muted small">{{ $flask->last_used_at ? 'last knocked out '.$flask->last_used_at->displayDate() : 'not used yet' }}</div></div>
            <div><div class="detail-label">Now</div><div class="detail-value">
                @if ($flask->currentMould)In mould {{ $flask->currentMould->assembly_number }}@if ($flask->currentMould->mouldingRun) · <a href="{{ route('moulding-runs.show', $flask->currentMould->mouldingRun) }}">run {{ $flask->currentMould->mouldingRun->run_number }}</a>@endif
                @elseif ($flask->currentRun)Held by <a href="{{ route('moulding-runs.show', $flask->currentRun) }}">run {{ $flask->currentRun->run_number }}</a>
                @else{{ \App\Models\Flask::STATUSES[$flask->status] ?? $flask->status }}@endif
            </div></div>
        </div>
        @if ($flask->notes)<p style="margin:14px 0 0">{{ $flask->notes }}</p>@endif
    </div>
    <div class="panel-body" style="border-top:1px solid var(--border);display:flex;flex-wrap:wrap;gap:10px;align-items:flex-start">
        @if ($flask->status === 'IN_MOULD')
            <form method="POST" action="{{ route('flasks.release', $flask) }}" onsubmit="return confirm('Release the flasks of this mould? Use this when it was knocked out without a shakeout record.')">@csrf<button class="btn" type="submit">Release from mould</button></form>
        @else
            @if ($flask->status !== 'REPAIR')
                <form method="POST" action="{{ route('flasks.status', $flask) }}" style="display:flex;gap:6px;align-items:flex-start">@csrf<input type="hidden" name="action" value="repair">
                    <input name="problem" required maxlength="2000" placeholder="What is wrong (e.g. pin worn, flange cracked)" style="min-width:280px"><button class="btn" type="submit">Send to repair</button></form>
            @endif
            @if ($flask->status !== 'AVAILABLE')
                <form method="POST" action="{{ route('flasks.status', $flask) }}">@csrf<input type="hidden" name="action" value="back"><button class="btn btn-primary" type="submit">{{ $flask->status === 'LOST' ? 'Found: back in service' : 'Back in service' }}</button></form>
            @endif
            @if (! in_array($flask->status, ['RETIRED', 'LOST'], true))
                <form method="POST" action="{{ route('flasks.status', $flask) }}" onsubmit="return confirm('Retire {{ $flask->code }}? It stays on record but is no longer used.')">@csrf<input type="hidden" name="action" value="retire"><button class="btn" type="submit">Retire</button></form>
                <form method="POST" action="{{ route('flasks.status', $flask) }}" onsubmit="return confirm('Mark {{ $flask->code }} as lost?')">@csrf<input type="hidden" name="action" value="lost"><button class="btn" type="submit">Mark lost</button></form>
            @endif
        @endif
    </div>
</div>

<div class="tab-marker" data-tab-start="Moulds"></div>
<div class="panel">
    <div class="panel-header"><h2>Moulds made in this flask</h2><span class="muted small">Latest 30</span></div>
    @if ($moulds->isEmpty())
        <div class="empty-state">Not used in a mould yet.</div>
    @else
        <div class="table-wrap"><table>
            <thead><tr><th>Mould</th><th>As</th><th>Pattern</th><th>Run</th><th>Pour</th><th>Status</th></tr></thead>
            <tbody>
                @foreach ($moulds as $layer)
                    @php $a = $layer->assembly; @endphp
                    <tr>
                        <td class="mono">{{ $a?->assembly_number }}</td>
                        <td>{{ ucfirst(strtolower($layer->layer_type)) }}</td>
                        <td>@if ($a?->mouldingRun?->patternRevision)<a href="{{ route('patterns.show', $a->mouldingRun->patternRevision->pattern) }}">{{ $a->mouldingRun->patternRevision->pattern->name }}</a> <span class="muted small mono">{{ $a->mouldingRun->patternRevision->code }}</span>@else — @endif</td>
                        <td>@if ($a?->mouldingRun)<a href="{{ route('moulding-runs.show', $a->mouldingRun) }}">{{ $a->mouldingRun->run_number }}</a>@endif</td>
                        <td>@if ($a?->pourAssignment?->pour)<a href="{{ route('pours.show', $a->pourAssignment->pour) }}">{{ $a->pourAssignment->pour->pour_number }}</a>@else — @endif</td>
                        <td>{{ str_replace('_', ' ', $a?->status ?? '') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table></div>
    @endif
</div>

<div class="tab-marker" data-tab-start="Maintenance"></div>
<div class="panel">
    <div class="panel-header"><h2>Maintenance</h2>@if (Route::has('tooling-life.dashboard'))<a class="small" href="{{ route('tooling-life.dashboard') }}">Life limits</a>@endif</div>
    @if ($maintenance->isEmpty())
        <div class="empty-state">No maintenance recorded.</div>
    @else
        <div class="table-wrap"><table>
            <thead><tr><th>Record</th><th>Activity</th><th>Status</th><th>Reported</th><th>Problem</th></tr></thead>
            <tbody>
                @foreach ($maintenance as $m)
                    <tr><td>@if (Route::has('tooling-maintenance.show'))<a href="{{ route('tooling-maintenance.show', $m) }}" class="mono">{{ $m->record_number }}</a>@else<span class="mono">{{ $m->record_number }}</span>@endif</td><td>{{ $m->activity_type }}</td><td>{{ $m->status }}</td><td>{{ $m->reported_at?->displayDate() }}</td><td class="small">{{ \Illuminate\Support\Str::limit($m->problem_description, 120) }}</td></tr>
                @endforeach
            </tbody>
        </table></div>
    @endif
</div>

<div class="tab-marker" data-tab-start="Files & history"></div>
@include('attachments._panel', ['type' => 'flask', 'id' => $flask->id])
@include('audit._history', ['type' => 'Flask', 'id' => $flask->id])
@endsection
