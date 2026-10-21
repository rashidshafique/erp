@extends('layouts.app')

@section('title', 'Casting analysis')
@section('breadcrumb', 'Casting analysis')

@section('content')
<div class="page-heading">
    <div>
        <h1>Casting analysis</h1>
        <p>Upload an STL or STEP model to see where it freezes last, how to feed it, how castable it is and what to melt.</p>
    </div>
    <div class="actions">
        <a class="btn btn-primary" href="{{ route('casting-analyses.create') }}">+ New analysis</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <form method="GET" action="{{ route('casting-analyses.index') }}" class="filters">
            <div class="field">
                <label for="search">Search code or name</label>
                <input id="search" name="search" value="{{ request('search') }}" placeholder="e.g. CAN-00012 or bracket">
            </div>
            <div class="field">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">All</option>
                    @foreach (['COMPLETE', 'FAILED'] as $s)
                        <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst(strtolower($s)) }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn btn-primary" type="submit">Filter</button>
            <a class="btn" href="{{ route('casting-analyses.index') }}">Reset</a>
        </form>
    </div>
</div>

<div class="panel">
    <div class="panel-header">
        <h2>Analyses</h2>
        <span class="muted small">{{ $rows->total() }} records</span>
    </div>
    @if ($rows->isEmpty())
        <div class="empty-state">
            <strong>No analyses yet</strong>
            <p>Export a part as STL from your CAD system and upload it. STEP files work when the converter is installed.</p>
            <a class="btn btn-primary" href="{{ route('casting-analyses.create') }}">Start an analysis</a>
        </div>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Code</th><th>Name</th><th>Product</th><th>Material</th><th>Score</th><th>Last to freeze</th><th>Status</th><th>When</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($rows as $r)
                        @php($rec = $r->recommendation)
                        <tr>
                            <td><strong>{{ $r->code }}</strong></td>
                            <td>{{ $r->name }}<div class="muted small">{{ $r->original_name }}</div></td>
                            <td>@if ($r->product)<x-product-cell :product="$r->product" />@else—@endif</td>
                            <td>{{ $rec['family_label'] ?? '—' }}</td>
                            <td>@if ($r->score !== null)<strong>{{ $r->score }}</strong> <span class="muted">({{ $r->score_letter }})</span>@else — @endif</td>
                            <td>@if ($rec){{ \App\Support\Formats::number($rec['times']['hotspots_min'][array_key_first($rec['times']['hotspots_min'])] ?? 0, 1) }} min @else — @endif</td>
                            <td><span class="status {{ $r->status === 'COMPLETE' ? 'status-completed' : 'status-cancelled' }}">{{ $r->status }}</span></td>
                            <td class="muted small">{{ $r->created_at->diffForHumans() }}</td>
                            <td><a href="{{ route('casting-analyses.show', $r) }}">Open →</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="pagination">{{ $rows->links() }}</div>
    @endif
</div>
@endsection
