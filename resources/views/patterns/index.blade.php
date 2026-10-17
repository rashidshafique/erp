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
        <a class="btn" href="{{ route('imports.create', ['entity' => 'pattern']) }}">Import</a> <a class="btn btn-primary" href="{{ route('patterns.create') }}">+ New pattern</a>
    </div>
</div>

<div class="metrics">
    <div class="metric">
        <div class="metric-label">Total patterns</div>
        <div class="metric-value">{{ number_format($totalPatterns) }}</div>
    </div>
    <div class="metric">
        <div class="metric-label">Active</div>
        <div class="metric-value">{{ number_format($activePatterns) }}</div>
    </div>
    <div class="metric">
        <div class="metric-label">Inactive / retired</div>
        <div class="metric-value">{{ number_format($otherPatterns) }}</div>
    </div>
    <div class="metric">
        <div class="metric-label">Matching results</div>
        <div class="metric-value">{{ number_format($patterns->total()) }}</div>
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
            <button class="btn btn-primary" type="submit">Filter</button>
            <a class="btn" href="{{ route('patterns.index') }}">Reset</a>
        </form>
    </div>
</div>

<div class="panel">
    <div class="panel-header">
        <h2>Patterns</h2>
        <span class="muted small">{{ $patterns->total() }} records @if ($baselineOnly && request('revision') !== 'baseline') · <a href="{{ route('patterns.index', ['revision' => 'baseline']) }}">{{ $baselineOnly }} still on the as-imported baseline with no drawing linked</a>@endif</span>
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
                        <th>Code</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Material</th>
                        <th>Current revision</th>
                        <th>Cavities / mould</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($patterns as $pattern)
                        @php($latestRevision = $pattern->currentRevision)
                        <tr>
                            <td><strong>{{ $pattern->code }}</strong></td>
                            <td>{{ $pattern->name }}</td>
                            <td>{{ $pattern->pattern_type }}</td>
                            <td>{{ $pattern->material ?: '—' }}</td>
                            <td>@if ($latestRevision)<span class="mono">{{ $latestRevision->code }}</span>@if ($latestRevision->legacy_baseline) <span class="status" title="As-imported baseline">Baseline</span>@endif @if ($latestRevision->drawing_revision)<div class="muted small">Drawing rev {{ $latestRevision->drawing_revision }}</div>@endif @else — @endif</td>
                            <td>{{ $latestRevision?->cavities_per_mould ?? '—' }}</td>
                            <td>
                                <span class="status {{ $pattern->status === 'ACTIVE' ? 'status-completed' : 'status-cancelled' }}">
                                    {{ $pattern->status }}
                                </span>
                            </td>
                            <td><a href="{{ route('patterns.show', $pattern) }}">Open →</a></td>
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
