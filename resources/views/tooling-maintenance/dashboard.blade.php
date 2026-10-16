@extends('layouts.app')

@section('title', "Maintenance Dashboard")
@section('breadcrumb', "Maintenance Dashboard")

@push('styles')
<style>
.legacy-page .links { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 18px; }
.legacy-page a { color: var(--accent); }
.legacy-page .btn { display: inline-block; padding: 10px 14px; background: var(--accent); color: white; text-decoration: none; border-radius: 6px; }
.legacy-page .cards { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; }
.legacy-page .card,
.legacy-page .panel { background: white; border: 1px solid #dfe5eb; border-radius: 10px; padding: 20px; margin-bottom: 18px; }
.legacy-page .label { color: #687586; font-size: 13px; }
.legacy-page .number { font-size: 30px; font-weight: 750; margin-top: 7px; overflow-wrap: anywhere; }
.legacy-page .table-wrap { overflow-x: auto; }
.legacy-page table { width: 100%; border-collapse: collapse; min-width: 750px; }
.legacy-page th,
.legacy-page td { text-align: left; padding: 11px; border-bottom: 1px solid #e7ebf0; font-size: 13px; }
.legacy-page th { background: #f8fafc; }
@media(max-width: 700px) {
.legacy-page .cards { grid-template-columns: repeat(2, minmax(0,1fr)); }
.legacy-page .number { font-size: 24px; }
}
@media(max-width: 420px) {
.legacy-page .cards { grid-template-columns: 1fr; }
}
</style>
@endpush

@section('content')
<div class="legacy-page">
<div class="page-heading"><div class="legacy-header">
<h1>Tooling Maintenance Dashboard</h1>
    <div>Foundry ERP · Maintenance control</div>
</div></div>
<div class="legacy-main">
    <nav class="links">
        <a class="btn" href="{{ route('tooling-maintenance.index') }}">Maintenance register</a>
        <a class="btn" href="{{ route('tooling-maintenance.create') }}">+ New record</a>
    </nav>

    <section class="cards">
        @foreach ([
            ['Active jobs', $openCount],
            ['Urgent active jobs', $urgentCount],
            ['Active breakdowns', $breakdownCount],
            ['Overdue inspections', $overdueCount],
            ['Recorded downtime (hours)', number_format($downtimeHours, 2)],
            ['All-time maintenance spend (PKR)', number_format($maintenanceSpend, 2)],
        ] as [$label, $value])
            <div class="card">
                <div class="label">{{ $label }}</div>
                <div class="number">{{ $value }}</div>
            </div>
        @endforeach
    </section>

    <section class="panel">
        <h2>Active maintenance jobs</h2>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Record</th>
                    <th>Tooling</th>
                    <th>Activity</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Reported</th>
                    <th>Downtime (h)</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($recentActive as $record)
                    @php
                        $item = $record->pattern?->name
                            ?? $record->patternRevision?->revision
                            ?? $record->coreDefinition?->name
                            ?? $record->coreBox?->name
                            ?? $record->mouldConfiguration?->name
                            ?? $record->mouldAssembly?->assembly_number
                            ?? 'Tooling item unavailable';
                    @endphp
                    <tr>
                        <td><a href="{{ route('tooling-maintenance.show', $record) }}">{{ $record->record_number }}</a></td>
                        <td>{{ $item }}</td>
                        <td>{{ $record->activity_type }}</td>
                        <td>{{ $record->priority }}</td>
                        <td>{{ $record->status }}</td>
                        <td>{{ $record->reported_at?->format('d M Y H:i') ?? '—' }}</td>
                        <td>{{ number_format((float) $record->downtime_hours, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7">No active maintenance jobs.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
</div>
@endsection
