@extends('layouts.app')

@section('title', "Tooling Maintenance History")
@section('breadcrumb', "Tooling Maintenance History")

@push('styles')
<style>
.legacy-page .container { max-width: 1250px; margin: auto; }
.legacy-page .card {
            background: white;
            border: 1px solid #e1e5eb;
            border-radius: 10px;
            padding: 18px;
            margin-bottom: 18px;
        }
.legacy-page .metrics {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(155px, 1fr));
            gap: 12px;
        }
.legacy-page .metric { font-size: 13px; color: #596273; }
.legacy-page .metric strong {
            display: block;
            margin-top: 8px;
            font-size: 24px;
            color: #202631;
        }
.legacy-page .table-wrap { overflow-x: auto; }
.legacy-page table { width: 100%; border-collapse: collapse; min-width: 800px; }
.legacy-page th,
.legacy-page td {
            text-align: left;
            padding: 12px 10px;
            border-bottom: 1px solid #e8ebef;
            vertical-align: top;
        }
.legacy-page th { font-size: 12px; color: #596273; }
.legacy-page a { color: #155eef; }
.legacy-page .muted { color: #667085; }
.legacy-page .actions { display: flex; gap: 16px; margin-bottom: 18px; }
</style>
@endpush

@section('content')
<div class="legacy-page">
<div class="container">
    <div class="actions">
        <a href="{{ route('tooling-maintenance.show', $toolingMaintenance) }}">
            ← Back to maintenance record
        </a>
        <a href="{{ route('tooling-maintenance.index') }}">
            All maintenance records
        </a>
    </div>

    <h1>Tooling Maintenance History</h1>

    <div class="card">
        <p>
            <strong>Tooling type:</strong>
            {{ $toolingMaintenance->tooling_type }}
        </p>
        <p>
            <strong>Tooling item:</strong>
            {{ $toolingMaintenance->record_number }}
        </p>
        <p class="muted">
            History is grouped by the exact tooling reference attached to
            this maintenance record.
        </p>
    </div>

    <div class="metrics">
        <div class="card metric">
            Total records
            <strong>{{ \App\Support\Formats::number($summary['total_records']) }}</strong>
        </div>
        <div class="card metric">
            Completed records
            <strong>{{ \App\Support\Formats::number($summary['completed_records']) }}</strong>
        </div>
        <div class="card metric">
            Breakdown records
            <strong>{{ \App\Support\Formats::number($summary['breakdowns']) }}</strong>
        </div>
        <div class="card metric">
            Recorded downtime
            <strong>{{ \App\Support\Formats::number((float) $summary['downtime_hours'], 2) }} h</strong>
        </div>
        <div class="card metric">
            Parts cost (PKR)
            <strong>{{ \App\Support\Formats::number((float) $summary['parts_cost'], 2) }}</strong>
        </div>
        <div class="card metric">
            Other cost (PKR)
            <strong>{{ \App\Support\Formats::number((float) $summary['other_cost'], 2) }}</strong>
        </div>
    </div>

    <div class="card">
        <h2>Maintenance events</h2>

        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Record</th>
                    <th>Reported</th>
                    <th>Activity</th>
                    <th>Status</th>
                    <th>Priority</th>
                    <th>Problem</th>
                    <th>Downtime</th>
                    <th>Recorded cost</th>
                    <th>Photos</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($records as $record)
                    <tr>
                        <td>
                            <a href="{{ route('tooling-maintenance.show', $record) }}">
                                {{ $record->record_number }}
                            </a>
                        </td>
                        <td>
                            {{ $record->reported_at?->format('Y-m-d H:i') ?? '—' }}
                        </td>
                        <td>{{ $record->activity_type }}</td>
                        <td>{{ $record->status }}</td>
                        <td>{{ $record->priority }}</td>
                        <td>{{ $record->problem_description ?: '—' }}</td>
                        <td>{{ \App\Support\Formats::number((float) $record->downtime_hours, 2) }} h</td>
                        <td>
                            {{ \App\Support\Formats::number(
                                (float) $record->parts_cost
                                + (float) $record->other_cost,
                                2
                            ) }}
                        </td>
                        <td>{{ $record->attachments_count }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9">No maintenance records found.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top: 18px;">
            {{ $records->links() }}
        </div>
    </div>
</div>
</div>
@endsection