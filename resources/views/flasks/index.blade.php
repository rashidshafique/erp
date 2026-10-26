@extends('layouts.app')

@section('title', 'Flasks')
@section('breadcrumb', 'Equipment & Tooling / Flasks')

@section('content')
@php
    $statusChip = ['AVAILABLE' => 'status-completed', 'IN_MOULD' => 'status-in-progress', 'REPAIR' => 'status-on-hold', 'RETIRED' => 'status-cancelled', 'LOST' => 'status-cancelled'];
    $positions = ['DRAG', 'COPE', 'STACK', 'CHEEK', 'ANY'];
@endphp
<div class="page-heading">
    <div><h1>Flasks</h1><p>Every flask by size: free, in a mould or in repair. Moulds claim a set when they are assembled and give it back when they are knocked out.</p></div>
    <div class="actions">
        @if (\App\Support\SettingsNav::canOpen())<a class="btn" href="{{ route('settings.flasks.edit') }}">Flask rules</a>@endif
        <button type="button" class="btn" data-quick="qd-flask-size">+ Flask size</button>
        <button type="button" class="btn btn-primary" data-quick="qd-flasks" @if ($sizes->isEmpty()) disabled title="Add a flask size first" @endif>+ Add flasks</button>
    </div>
</div>
@include('partials.quick-dialog', ['id' => 'qd-flask-size', 'title' => 'Add a flask size', 'action' => route('flask-sizes.store'), 'submit' => 'Add size', 'fields' => 'flasks._quick-size'])
@include('partials.quick-dialog', ['id' => 'qd-flasks', 'title' => 'Add flasks', 'action' => route('flasks.store'), 'submit' => 'Add flasks', 'fields' => 'flasks._quick-flasks', 'sizes' => $sizes])

<style>
    .fb-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 12px; }
    .fb-card { border: 1px solid var(--border); border-radius: 10px; padding: 12px 14px; background: var(--surface); }
    .fb-card h3 { margin: 0; font-size: 15px; display: flex; justify-content: space-between; gap: 8px; }
    .fb-card .dim { font-size: 12px; color: var(--muted); margin: 2px 0 8px; }
    .fb-card table { width: 100%; font-size: 12.5px; border-collapse: collapse; }
    .fb-card th, .fb-card td { padding: 3px 4px; text-align: right; }
    .fb-card th:first-child, .fb-card td:first-child { text-align: left; }
    .fb-card th { font-size: 10.5px; color: var(--muted); text-transform: uppercase; letter-spacing: .04em; font-weight: 600; }
    .fb-card .free { font-weight: 700; color: var(--accent); }
</style>

<div class="tab-marker" data-tab-start="Board"></div>
<div class="panel">
    <div class="panel-header"><h2>Flask board</h2><span class="muted small">Free / in a mould / in repair, by position</span></div>
    <div class="panel-body">
        @if ($sizes->isEmpty())
            <div class="empty-state">No flask sizes yet. Add the sizes you use (e.g. 600 × 500), then add the flasks of each size.</div>
        @else
            <div class="fb-grid">
                @foreach ($sizes as $size)
                    @php $c = ($counts[$size->id] ?? collect())->groupBy('position'); $n = fn ($pos, $status) => (int) ($c[$pos] ?? collect())->where('status', $status)->sum('n'); @endphp
                    <div class="fb-card">
                        <h3><a href="{{ route('flasks.index', ['size' => $size->id]) }}">{{ $size->name }}</a>@include('partials.icon-button', ['type' => 'edit', 'name' => $size->name, 'href' => route('flask-sizes.edit', $size)])</h3>
                        <div class="dim">{{ $size->dimensions() ?: 'No dimensions recorded' }}@if ($size->is_block) · <span class="pill">Block size</span>@endif @if ($size->status !== 'ACTIVE') · <span class="status status-cancelled">inactive</span>@endif</div>
                        @if ($size->is_block)<p class="muted small" style="margin:6px 0 0">A block size for flaskless moulds: no flasks are counted.</p>
                        @elseif ($manager->isCountOnly($size))
                        @php $inUse = $manager->setsInUse($size); @endphp
                        <table>
                            <thead><tr><th>Sets owned</th><th>Free</th><th>In use</th></tr></thead>
                            <tbody><tr><td>{{ $size->sets_owned }}</td><td class="free">{{ max(0, $size->sets_owned - $inUse) }}</td><td>{{ $inUse }}</td></tr></tbody>
                        </table>
                        <p class="muted small" style="margin:6px 0 0">Counted, not registered flask by flask.</p>
                        @else
                        <table>
                            <thead><tr><th>Position</th><th>Free</th><th>In mould</th><th>Repair</th></tr></thead>
                            <tbody>
                                @foreach ($positions as $pos)
                                    @continue(! isset($c[$pos]))
                                    <tr><td>{{ \Illuminate\Support\Str::before(\App\Models\Flask::POSITIONS[$pos], ' (') }}</td><td class="free">{{ $n($pos, 'AVAILABLE') }}</td><td>{{ $n($pos, 'IN_MOULD') }}</td><td>{{ $n($pos, 'REPAIR') }}</td></tr>
                                @endforeach
                                @if ($c->isEmpty())<tr><td colspan="4" class="muted">No flasks of this size yet.</td></tr>@endif
                            </tbody>
                        </table>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

@include('flasks._load', ['load' => $load, 'title' => 'Sets wanted by open work lists'])

<div class="tab-marker" data-tab-start="Register"></div>
<div class="panel">
    <div class="panel-body">
        <form method="GET" action="{{ route('flasks.index') }}" class="filters">
            <div class="field"><label for="search">Code, mark or location</label><input id="search" name="search" value="{{ request('search') }}"></div>
            <div class="field"><label for="size">Size</label><select id="size" name="size"><option value="">All sizes</option>@foreach ($sizes as $s)<option value="{{ $s->id }}" @selected(request('size') === $s->id)>{{ $s->name }}</option>@endforeach</select></div>
            <div class="field"><label for="position">Position</label><select id="position" name="position"><option value="">Any</option>@foreach (\App\Models\Flask::POSITIONS as $k => $l)<option value="{{ $k }}" @selected(request('position') === $k)>{{ $l }}</option>@endforeach</select></div>
            <div class="field"><label for="status">Status</label><select id="status" name="status"><option value="">Any</option>@foreach (\App\Models\Flask::STATUSES as $k => $l)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach</select></div>
            <button class="btn btn-primary" type="submit">Filter</button>
            <a class="btn" href="{{ route('flasks.index') }}">Reset</a>
        </form>
    </div>
</div>
<div class="panel">
    <div class="panel-header"><h2>Flask register</h2><span class="muted small">{{ $flasks->total() }} {{ $flasks->total() === 1 ? 'flask' : 'flasks' }}</span></div>
    @if ($flasks->isEmpty())
        <div class="empty-state">No flasks match.</div>
    @else
        <div class="table-wrap"><table>
            <thead><tr><th>Flask</th><th>Mark</th><th>Size</th><th>Position</th><th>Status</th><th>Where</th><th>Uses</th><th>Partner</th></tr></thead>
            <tbody>
                @foreach ($flasks as $f)
                    <tr>
                        <td><a href="{{ route('flasks.show', $f) }}"><strong class="mono">{{ $f->code }}</strong></a></td>
                        <td class="mono">{{ $f->mark_code ?? '—' }}</td>
                        <td>{{ $f->size?->name }}</td>
                        <td>{{ \Illuminate\Support\Str::before(\App\Models\Flask::POSITIONS[$f->position] ?? $f->position, ' (') }}</td>
                        <td><span class="status {{ $statusChip[$f->status] ?? '' }}">{{ \App\Models\Flask::STATUSES[$f->status] ?? $f->status }}</span></td>
                        <td class="small">@if ($f->currentMould)Mould {{ $f->currentMould->assembly_number }}@elseif ($f->currentRun)Run {{ $f->currentRun->run_number }}@else{{ $f->location ?: '—' }}@endif</td>
                        <td class="mono">{{ \App\Support\Formats::number($f->cycles) }}</td>
                        <td class="mono small">{{ $f->partner?->code ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table></div>
        <div class="pagination"><div class="muted small">Showing {{ $flasks->firstItem() }}–{{ $flasks->lastItem() }} of {{ $flasks->total() }}</div><div class="pagination-links">{{ $flasks->links() }}</div></div>
    @endif
</div>

@endsection
