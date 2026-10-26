@extends('layouts.app')

@section('title', 'Patterns')
@section('breadcrumb', 'Patterns')

@section('content')
<div class="page-heading">
    <div>
        <h1>Pattern master</h1>
        <p>Manage physical tooling, pattern revisions and moulding configurations.</p>
    </div>
    <div class="actions">
        @if ($withoutRevision > 0)
            <form method="POST" action="{{ route('patterns.first-revisions.store') }}" style="display:inline" onsubmit="return confirm(@js('Create Rev 0 for the '.$withoutRevision.' '.($withoutRevision === 1 ? 'pattern' : 'patterns').' that have no revision? Each gets a current, approved Rev 0 with the mould arrangement its type is usually used for.'))">@csrf
                <button class="btn" type="submit" title="Patterns that already have a revision are not touched">Create Rev 0 for {{ $withoutRevision }} {{ $withoutRevision === 1 ? 'pattern' : 'patterns' }} without a revision</button></form>
        @endif
        @if ($unlinkedPatterns > 0 && request('link') !== 'unlinked')
            <a class="btn" href="{{ route('patterns.index', ['link' => 'unlinked']) }}" title="Lists the patterns with no cavity linked to a product; open one and use ← → to go through them">Fix {{ $unlinkedPatterns }} unlinked {{ $unlinkedPatterns === 1 ? 'pattern' : 'patterns' }}</a>
        @endif
        @if ($unlinkedPatterns > 0)
            <a class="btn" href="{{ route('patterns.bulk-link') }}" title="Pick the product, cavity count and product revision of every unlinked pattern on one screen">Link in bulk</a>
        @endif
        <a class="btn" href="{{ route('pattern-ownership.index') }}" style="display:inline-flex;align-items:center;gap:6px">{!! \App\Support\NavIcons::get('users', 15) !!}Pattern ownership</a> <a class="btn" href="{{ route('imports.create', ['entity' => 'pattern']) }}">Import</a> <a class="btn btn-primary" href="{{ route('patterns.create') }}">+ New pattern</a>
    </div>
</div>

<div class="metrics">
    <div class="metric">
        <div class="metric-label">Total patterns</div>
        <div class="metric-value">{{ \App\Support\Formats::number($totalPatterns) }}</div>
    </div>
    <div class="metric">
        <div class="metric-label">Active</div>
        <div class="metric-value">{{ \App\Support\Formats::number($activePatterns) }}</div>
    </div>
    <div class="metric">
        <div class="metric-label">Inactive / retired</div>
        <div class="metric-value">{{ \App\Support\Formats::number($otherPatterns) }}</div>
    </div>
    <div class="metric">
        <div class="metric-label">Need a product link</div>
        <div class="metric-value"><a href="{{ route('patterns.index', ['link' => 'unlinked']) }}">{{ \App\Support\Formats::number($unlinkedPatterns) }}</a></div>
        @if ($unlinkedPatterns > 10)<div class="metric-note">Many? <a href="{{ route('imports.create', ['entity' => 'pattern_cavity']) }}">Import the cavities from a spreadsheet</a></div>@endif
    </div>
    <div class="metric">
        <div class="metric-label">Matching results</div>
        <div class="metric-value">{{ \App\Support\Formats::number($patterns->total()) }}</div>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <form method="GET" action="{{ route('patterns.index') }}" class="filters">
            <div class="field">
                <label for="search">Search pattern code or name</label>
                <input id="search" name="search" value="{{ request('search') }}"
                       placeholder="e.g. VALVE-001">
            </div>
            <div class="field">
                <label for="status">Tooling status</label>
                <select id="status" name="status">
                    <option value="">All statuses</option>
                    @foreach (['ACTIVE', 'INACTIVE', 'RETIRED'] as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>
                            {{ $status }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="revision">Revision</label>
                <select id="revision" name="revision">
                    <option value="">Any revision</option>
                    <option value="baseline" @selected(request('revision') === 'baseline')>Baseline only, no drawing linked</option>
                </select>
            </div>
            <div class="field">
                <label for="owner">Owner</label>
                <select id="owner" name="owner">
                    <option value="">Any owner</option>
                    @foreach (\App\Models\Pattern::OWNERS as $k => $l)<option value="{{ $k }}" @selected(request('owner') === $k)>{{ $k === 'FOUNDRY' ? 'Foundry' : ($k === 'CUSTOMER' ? 'Any customer' : 'Being paid off') }}</option>@endforeach
                    @foreach ($owningCustomers as $c)<option value="{{ $c->id }}" @selected(request('owner') === $c->id)>{{ $c->name }}</option>@endforeach
                </select>
            </div>
            <div class="field">
                <label for="link">Product link</label>
                <select id="link" name="link"><option value="">Any</option><option value="unlinked" @selected(request('link') === 'unlinked')>Needs a product link</option></select>
            </div>
            <button class="btn btn-primary" type="submit">Filter</button>
            <a class="btn" href="{{ route('patterns.index') }}">Reset</a>
        </form>
    </div>
</div>

<div class="panel">
    <div class="panel-header">
        <h2>{{ $archived ? 'Archived patterns' : 'Patterns' }}</h2>
        <span class="muted small">{{ $patterns->total() }} records · @if ($archived)<a href="{{ route('patterns.index') }}">Back to current patterns</a>@elseif ($archivedPatterns)<a href="{{ route('patterns.index', ['archived' => 1]) }}">{{ $archivedPatterns }} archived</a>@else none archived @endif @if ($baselineOnly && request('revision') !== 'baseline') · <a href="{{ route('patterns.index', ['revision' => 'baseline']) }}">{{ $baselineOnly }} still on the as-imported baseline with no drawing linked</a>@endif</span>
    </div>

    @if ($patterns->isEmpty())
        <div class="empty-state">
            <strong>No patterns found</strong>
            <p>Create a pattern and its first engineering revision.</p>
            <a class="btn btn-primary" href="{{ route('patterns.create') }}">Create pattern</a>
        </div>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Pattern</th>
                        <th>Type</th>
                        <th>Material</th>
                        <th>Current revision</th>
                        <th>Cavities / mould</th>
                        <th>Status</th>
                        <th>Owner · where</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($patterns as $pattern)
                        @php($latestRevision = $pattern->currentRevision)
                        <tr>
                            <td><x-item :name="$pattern->name" :code="$pattern->code" /></td>
                            <td>{!! \App\Support\MouldIcons::patternType($pattern->pattern_type, 17) !!} {{ \App\Models\Pattern::typeName($pattern->pattern_type, true) }}</td>
                            <td>{{ $pattern->material ?: '—' }}</td>
                            <td>@if ($latestRevision)<span class="mono">{{ $latestRevision->code }}</span>@if ($latestRevision->legacy_baseline) <span class="status" title="As-imported baseline">Baseline</span>@endif @if ($latestRevision->drawing_revision)<div class="muted small">Drawing rev {{ $latestRevision->drawing_revision }}</div>@endif @else — @endif</td>
                            <td>{{ $latestRevision?->cavities_per_mould ?? '—' }}</td>
                            <td>
                                <span class="status {{ $pattern->status === 'ACTIVE' ? 'status-completed' : 'status-cancelled' }}">
                                    {{ $pattern->status }}
                                </span>
                            </td>
                            <td class="small">{{ $pattern->owner_type === 'FOUNDRY' ? 'Foundry' : $pattern->ownerLabel() }}@if ($pattern->custody !== 'IN_STORE')<div class="muted">{{ \App\Models\Pattern::CUSTODIES[$pattern->custody] ?? $pattern->custody }}</div>@endif</td>
                            <td style="white-space:nowrap"><a href="{{ route('patterns.show', array_filter(['pattern' => $pattern, 'link' => request('link') === 'unlinked' ? 'unlinked' : null])) }}">Open →</a>@if ($archived)<form method="POST" action="{{ route('patterns.restore', $pattern) }}" style="display:inline;margin-left:8px">@csrf<button class="btn btn-small" type="submit">Restore</button></form>@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="pagination">
            <div class="muted small">
                Showing {{ $patterns->firstItem() }}–{{ $patterns->lastItem() }}
                of {{ $patterns->total() }}
            </div>
            <div class="pagination-links">{{ $patterns->links() }}</div>
        </div>
    @endif
</div>
@endsection
