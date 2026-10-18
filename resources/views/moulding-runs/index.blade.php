@extends('layouts.app')

@section('title', 'Moulding runs')
@section('breadcrumb', 'Moulding runs')

@section('content')
    <div class="page-heading">
        <div>
            <h1>Moulding runs</h1>
            <p>Record mould production, operator/team, method, downtime and damaged moulds.</p>
        </div>
        <div class="actions">
            <a href="{{ route('moulding-runs.create') }}" class="btn btn-primary">
                + Record moulding run
            </a>
        </div>
    </div>
    @include('moulding-runs._tabs', ['active' => 'runs'])

    <div class="metrics">
        <div class="metric">
            <div class="metric-label">Runs today</div>
            <div class="metric-value">{{ \App\Support\Formats::number($todayRuns) }}</div>
            <div class="metric-note">Moulding run records</div>
        </div>
        <div class="metric">
            <div class="metric-label">Runs in progress</div>
            <div class="metric-value">{{ \App\Support\Formats::number($inProgressRuns) }}</div>
            <div class="metric-note">Currently marked in progress</div>
        </div>
        <div class="metric">
            <div class="metric-label">Moulds made today</div>
            <div class="metric-value">{{ \App\Support\Formats::number($todayMoulds) }}</div>
            <div class="metric-note">Total recorded output</div>
        </div>
        <div class="metric">
            <div class="metric-label">Damaged today</div>
            <div class="metric-value">{{ \App\Support\Formats::number($todayDamaged) }}</div>
            <div class="metric-note">Mould damage, not casting rejection</div>
        </div>
    </div>

    <section class="panel">
        <div class="panel-header">
            <h2>Find moulding runs</h2>
        </div>
        <div class="panel-body">
            <form method="GET" action="{{ route('moulding-runs.index') }}" class="filters">
                <div class="field">
                    <label for="search">Run, order or operator</label>
                    <input id="search" type="search" name="search"
                           value="{{ request('search') }}"
                           placeholder="e.g. MR-20261010 or operator">
                </div>

                <div class="field">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="">All statuses</option>
                        @foreach (['IN_PROGRESS', 'COMPLETED', 'ON_HOLD', 'CANCELLED'] as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>
                                {{ str_replace('_', ' ', $status) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="date_from">From date</label>
                    <input id="date_from" type="date" name="date_from"
                           value="{{ request('date_from') }}">
                </div>

                <div class="field">
                    <label for="date_to">To date</label>
                    <input id="date_to" type="date" name="date_to"
                           value="{{ request('date_to') }}">
                </div>

                <button class="btn btn-primary" type="submit">Apply filters</button>
                <a class="btn" href="{{ route('moulding-runs.index') }}">Clear</a>
            </form>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <h2>Recorded runs</h2>
            <span class="muted small">{{ $runs->total() }} total</span>
        </div>

        @if ($runs->count())
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Run</th>
                        <th>Date / shift</th>
                        <th>Production order</th>
                        <th>Pattern</th>
                        <th>Operator / team</th>
                        <th>Moulds made</th>
                        <th>Accepted</th>
                        <th>Damaged</th>
                        <th>Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($runs as $run)
                        <tr>
                            <td>
                                <a href="{{ route('moulding-runs.show', $run) }}">
                                    <strong>{{ $run->run_number }}</strong>
                                </a>
                            </td>
                            <td>
                                {{ $run->run_date?->displayDate() ?? '—' }}
                                <div class="muted small">{{ $run->shift ?: 'No shift' }}</div>
                            </td>
                            <td>
                                {{ $run->productionOrder?->production_order_number ?? '—' }}
                                <div class="muted small">
                                    {{ $run->productionOrder?->product?->name ?? 'Product not set' }}
                                </div>
                            </td>
                            <td>
                                {{ $run->patternRevision?->pattern?->name ?? '—' }}
                                @if ($run->mouldConfiguration)
                                    <div class="muted small">{{ $run->mouldConfiguration->name }}</div>
                                @endif
                            </td>
                            <td>
                                {{ $run->operator_name ?: '—' }}
                                <div class="muted small">{{ $run->team_name ?: 'No team' }}</div>
                            </td>
                            <td>{{ \App\Support\Formats::number($run->moulds_made_qty) }}</td>
                            <td>{{ \App\Support\Formats::number($run->accepted_mould_qty) }}</td>
                            <td>{{ \App\Support\Formats::number($run->damaged_mould_qty) }}</td>
                            <td>
                                <span class="status status-{{ strtolower(str_replace('_', '-', $run->status)) }}">
                                    {{ str_replace('_', ' ', $run->status) }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="pagination">
                <div class="muted small">
                    Showing {{ $runs->firstItem() }}–{{ $runs->lastItem() }}
                    of {{ $runs->total() }}
                </div>
                <div class="pagination-links">
                    @if ($runs->onFirstPage())
                        <span class="muted">Previous</span>
                    @else
                        <a href="{{ $runs->previousPageUrl() }}">Previous</a>
                    @endif

                    <span class="current">Page {{ $runs->currentPage() }} of {{ $runs->lastPage() }}</span>

                    @if ($runs->hasMorePages())
                        <a href="{{ $runs->nextPageUrl() }}">Next</a>
                    @else
                        <span class="muted">Next</span>
                    @endif
                </div>
            </div>
        @else
            <div class="empty-state">
                <strong>No moulding runs found.</strong>
                <p>Try clearing the filters or create your first moulding run.</p>
                <a href="{{ route('moulding-runs.create') }}" class="btn btn-primary">
                    Record first run
                </a>
            </div>
        @endif
    </section>
@endsection