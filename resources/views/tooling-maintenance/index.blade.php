@extends('layouts.app')

@section('title', "Tooling Maintenance")
@section('breadcrumb', "Tooling Maintenance")

@push('styles')
<style>
.legacy-page .legacy-header h1 { margin: 0 0 6px; font-size: 24px; }
.legacy-page .legacy-header p { margin: 0; color: #c5d0dc; font-size: 14px; }
.legacy-page .panel {
            background: white;
            border: 1px solid #e0e5eb;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
.legacy-page .filters {
            display: grid;
            grid-template-columns: repeat(4, minmax(150px, 1fr)) auto auto;
            gap: 12px;
            align-items: end;
        }
.legacy-page label { display: block; font-size: 12px; font-weight: 600; margin-bottom: 6px; }
.legacy-page select,
.legacy-page input,
.legacy-page button,
.legacy-page .button-link {
            width: 100%;
            min-height: 40px;
            border: 1px solid #ccd4de;
            border-radius: 6px;
            padding: 9px 10px;
            font: inherit;
            background: white;
        }
.legacy-page button,
.legacy-page .button-link {
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            display: inline-block;
            background: var(--accent);
            color: white;
            border-color: var(--accent);
        }
.legacy-page .button-secondary { background: white; color: var(--accent); }
.legacy-page .summary { display: flex; justify-content: space-between; gap: 12px; align-items: center; }
.legacy-page .summary h2 { font-size: 18px; margin: 0; }
.legacy-page .muted { color: #687586; font-size: 13px; }
.legacy-page .table-wrap { overflow-x: auto; }
.legacy-page table { border-collapse: collapse; width: 100%; min-width: 1100px; }
.legacy-page th,
.legacy-page td {
            padding: 12px 10px;
            border-bottom: 1px solid #e7ebf0;
            text-align: left;
            vertical-align: top;
            font-size: 13px;
        }
.legacy-page th {
            background: #f8fafc;
            color: #586575;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .04em;
        }
.legacy-page .record-number { font-weight: 700; color: var(--accent); }
.legacy-page .badge {
            display: inline-block;
            border-radius: 20px;
            padding: 4px 8px;
            background: #edf1f5;
            font-size: 11px;
            white-space: nowrap;
        }
.legacy-page .status-open,
.legacy-page .priority-high,
.legacy-page .priority-urgent { background: #fff0dc; color: #8a4b08; }
.legacy-page .status-completed { background: #e1f5e9; color: #17653a; }
.legacy-page .status-cancelled { background: #fce8e8; color: #9b2424; }
.legacy-page .empty { padding: 36px 16px; text-align: center; color: #687586; }
.legacy-page .pagination { padding-top: 16px; }
@media (max-width: 1024px) {
.legacy-page .filters { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 768px) {
.legacy-page .filters { grid-template-columns: 1fr; }
.legacy-page .summary { align-items: flex-start; flex-direction: column; }
}
</style>
@endpush

@section('content')
<div class="legacy-page">
<div class="page-heading"><div class="legacy-header">
<h1>Tooling Maintenance</h1>
    <p>Patterns, core boxes, mould configurations and assemblies</p>
</div></div>

<div class="legacy-main">
    <section class="panel">
        <form method="GET" action="{{ route('tooling-maintenance.index') }}" class="filters">
            <div>
                <label for="status">Status</label>
                <select name="status" id="status">
                    <option value="">All statuses</option>
                    @foreach (['OPEN', 'IN_PROGRESS', 'ON_HOLD', 'COMPLETED', 'CANCELLED'] as $option)
                        <option value="{{ $option }}" @selected(($filters['status'] ?? '') === $option)>
                            {{ str_replace('_', ' ', $option) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="priority">Priority</label>
                <select name="priority" id="priority">
                    <option value="">All priorities</option>
                    @foreach (['LOW', 'NORMAL', 'HIGH', 'URGENT'] as $option)
                        <option value="{{ $option }}" @selected(($filters['priority'] ?? '') === $option)>
                            {{ $option }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="activity_type">Activity</label>
                <select name="activity_type" id="activity_type">
                    <option value="">All activities</option>
                    @foreach (['INSPECTION', 'REPAIR', 'PREVENTIVE', 'MODIFICATION', 'BREAKDOWN'] as $option)
                        <option value="{{ $option }}" @selected(($filters['activity_type'] ?? '') === $option)>
                            {{ str_replace('_', ' ', $option) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="tooling_type">Tooling type</label>
                <select name="tooling_type" id="tooling_type">
                    <option value="">All tooling</option>
                    @foreach (['PATTERN', 'PATTERN_REVISION', 'CORE_DEFINITION', 'CORE_BOX', 'MOULD_CONFIGURATION', 'MOULD_ASSEMBLY', 'FLASK'] as $option)
                        <option value="{{ $option }}" @selected(($filters['tooling_type'] ?? '') === $option)>
                            {{ str_replace('_', ' ', $option) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <button type="submit">Apply filters</button>
            <a class="button-link button-secondary" href="{{ route('tooling-maintenance.index') }}">Clear</a>
        </form>
    </section>

    <section class="panel">
        <div class="summary" style="margin-bottom: 16px;">
            <div>
                <h2>Maintenance records</h2>
                <div class="muted">{{ $records->total() }} record(s) found</div>
            </div>
            <a class="button-link" href="{{ route('tooling-maintenance.create') }}">+ New maintenance record</a>
            <a class="button-link button-secondary" href="{{ route('tooling-maintenance.dashboard') }}">
                Maintenance dashboard
            </a>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Record</th>
                    <th>Tooling item</th>
                    <th>Activity / priority</th>
                    <th>Status</th>
                    <th>Problem</th>
                    <th>Reported</th>
                    <th>Performed by</th>
                    <th>Downtime</th>
                    <th>Total cost</th>
                    <th>Next inspection</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($records as $record)
                    @php
                        $toolingItem = $record->pattern?->name
                            ?? $record->patternRevision?->revision
                            ?? $record->coreBox?->name
                            ?? $record->coreDefinition?->name
                            ?? $record->mouldConfiguration?->name
                            ?? $record->mouldAssembly?->assembly_number
                            ?? 'Tooling item unavailable';

                        $totalCost = (float) $record->parts_cost + (float) $record->other_cost;
                    @endphp
                    <tr>
                        <td>
                            <div class="record-number"><a href="{{ route('tooling-maintenance.show', $record) }}">
        {{ $record->record_number }}
    </a></div>
                            <div class="muted">{{ $record->tooling_type }}</div>
                        </td>
                        <td>{{ $toolingItem }}</td>
                        <td>
                            <div>{{ str_replace('_', ' ', $record->activity_type) }}</div>
                            <span class="badge">{{ $record->priority }}</span>
                        </td>
                        <td>
                            <span class="badge
                                {{ $record->status === 'COMPLETED' ? 'status-completed' : '' }}
                                {{ $record->status === 'CANCELLED' ? 'status-cancelled' : '' }}
                                {{ $record->status === 'OPEN' ? 'status-open' : '' }}">
                                {{ str_replace('_', ' ', $record->status) }}
                            </span>
                        </td>
                        <td>{{ \Illuminate\Support\Str::limit($record->problem_description ?? '—', 90) }}</td>
                        <td>{{ $record->reported_at?->displayDateTime() ?? '—' }}</td>
                        <td>{{ $record->performed_by ?? '—' }}</td>
                        <td>{{ \App\Support\Formats::number((float) $record->downtime_hours, 2) }} h</td>
                        <td>{{ \App\Support\Formats::number($totalCost, 2) }}</td>
                        <td>{{ $record->next_inspection_due_on?->displayDate() ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="empty">
                            No maintenance records found. Try changing the filters.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination">
            {{ $records->links() }}
        </div>
    </section>
</div>
</div>
@endsection
