@extends('layouts.app')

@section('title', "Tooling Life & Usage")
@section('breadcrumb', "Tooling Life & Usage")

@push('styles')
<style>
.legacy-page .legacy-header h1 { margin: 0 0 4px; font-size: 24px; }
.legacy-page .legacy-header p { margin: 0; color: #c6d2dc; }
.legacy-page .summary {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 22px;
        }
.legacy-page .card {
            background: white;
            border: 1px solid #e1e6eb;
            border-radius: 9px;
            padding: 18px;
            box-shadow: 0 2px 5px #172b3a08;
        }
.legacy-page .metric-label { color: #5c6875; font-size: 13px; }
.legacy-page .metric { font-size: 29px; font-weight: 700; margin-top: 5px; }
.legacy-page .table-card { padding: 0; overflow: hidden; }
.legacy-page .table-heading { padding: 18px 20px; border-bottom: 1px solid #e1e6eb; }
.legacy-page .table-heading h2 { margin: 0; font-size: 17px; }
.legacy-page .table-wrap { overflow-x: auto; }
.legacy-page table {
            width: 100%;
            border-collapse: collapse;
            white-space: nowrap;
        }
.legacy-page th,
.legacy-page td {
            padding: 13px 15px;
            text-align: left;
            border-bottom: 1px solid #edf0f3;
        }
.legacy-page th { background: #f8fafb; color: #52606d; font-size: 12px; }
.legacy-page tbody tr:last-child td { border-bottom: 0; }
.legacy-page .status {
            display: inline-block;
            padding: 4px 9px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
.legacy-page .ok { background: #e4f5e9; color: #176b35; }
.legacy-page .warning { background: #fff1cf; color: #845900; }
.legacy-page .danger { background: #fde5e5; color: #a32020; }
.legacy-page .neutral { background: #eef1f4; color: var(--muted); }
.legacy-page .empty { padding: 30px; text-align: center; color: #697785; }
@media (max-width: 768px) {
.legacy-page .summary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 768px) {
.legacy-page .summary { grid-template-columns: 1fr 1fr; gap: 8px; }
.legacy-page .card { padding: 12px; }
.legacy-page .metric { font-size: 24px; }
}
</style>
@endpush

@section('content')
<div class="legacy-page">
<div class="page-heading"><div class="legacy-header">
<h1>Tooling Life & Usage</h1>
    <p>Service-life monitoring, usage tracking and inspection alerts</p>
    <a href="{{ route('tooling-life.create') }}"
   style="display:inline-block;margin-top:14px;padding:9px 14px;background:var(--accent);color:white;text-decoration:none;border-radius:6px;font-weight:600">
    + Configure tooling life
</a>
</div></div>

<div class="legacy-main">
    <section class="summary">
        <div class="card">
            <div class="metric-label">Inspection due</div>
            <div class="metric">{{ $inspectionDueCount }}</div>
        </div>

        <div class="card">
            <div class="metric-label">Life limit exceeded</div>
            <div class="metric">{{ $lifeExceededCount }}</div>
        </div>

        <div class="card">
            <div class="metric-label">Approaching limit</div>
            <div class="metric">{{ $approachingCount }}</div>
        </div>

        <div class="card">
            <div class="metric-label">Within limits</div>
            <div class="metric">{{ $withinLimitsCount }}</div>
        </div>
    </section>

    <section class="card table-card">
        <div class="table-heading">
            <h2>Tooling status</h2>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Tooling ID</th>
                        <th>Total usage</th>
                        <th>Since inspection</th>
                        <th>Service life used</th>
                        <th>Inspection interval used</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($items as $item)
                        @php
                            $limit = $item['limit'];

                            $toolingId = $limit->pattern_id
                                ?? $limit->pattern_revision_id
                                ?? $limit->core_definition_id
                                ?? $limit->core_box_id
                                ?? $limit->mould_configuration_id
                                ?? $limit->mould_assembly_id
                                ?? $limit->flask_id;

                            $statusClass = match ($item['status']) {
                                'OK' => 'ok',
                                'APPROACHING_LIMIT' => 'warning',
                                'INSPECTION_DUE', 'LIFE_EXCEEDED' => 'danger',
                                default => 'neutral',
                            };
                        @endphp

                        <tr>
                            <td>{{ str_replace('_', ' ', $limit->tooling_type) }}</td>
                            <td>{{ $toolingId }}</td>

                            <td>
                                {{ number_format($item['current_usage'], 3) }}
                                {{ $limit->usage_unit }}
                            </td>

                            <td>
                                {{ number_format($item['usage_since_inspection'], 3) }}
                                {{ $limit->usage_unit }}
                            </td>

                            <td>
                                {{ $item['life_percent'] === null
                                    ? 'Not configured'
                                    : number_format($item['life_percent'], 1) . '%' }}
                            </td>

                            <td>
                                {{ $item['inspection_percent'] === null
                                    ? 'Not configured'
                                    : number_format($item['inspection_percent'], 1) . '%' }}
                            </td>

                            <td>
                                <span class="status {{ $statusClass }}">
                                    {{ $item['status_label'] }}
                                </span>
                            </td>
                            <td>
                                @include('partials.icon-button', ['type' => 'edit', 'href' => route('tooling-life.edit', $limit)])
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="empty">
                                No active tooling life limits configured yet.
                                Configure a life limit to start monitoring a tool.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
</div>
@endsection
