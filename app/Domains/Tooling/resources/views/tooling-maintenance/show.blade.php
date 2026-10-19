@extends('layouts.app')

@php
    use Illuminate\Support\Facades\Storage;
@endphp
@section('title', "" . ( $record->record_number ) . "")
@section('breadcrumb', "Tooling")

@push('styles')
<style>
.legacy-page .panel { background: white; border: 1px solid #dfe5eb; border-radius: 10px; padding: 22px; margin-bottom: 16px; }
.legacy-page .grid { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 18px; }
.legacy-page .label { color: #687586; font-size: 12px; margin-bottom: 5px; }
.legacy-page .value { white-space: pre-wrap; overflow-wrap: anywhere; }
.legacy-page .btn { display: inline-block; padding: 10px 15px; border-radius: 6px; background: var(--accent); color: white; text-decoration: none; }
.legacy-page .success { background: #e1f5e9; padding: 12px; border-radius: 6px; margin-bottom: 14px; }
@media(max-width:768px) {
.legacy-page .grid { grid-template-columns: 1fr; }
}
</style>
@endpush

@section('content')
<div class="legacy-page">
<div class="page-heading"><div class="legacy-header">
<h1>{{ $record->record_number }}</h1>
    <div>{{ str_replace('_', ' ', $record->activity_type) }} · {{ str_replace('_', ' ', $record->status) }}</div>
</div></div>
<div class="legacy-main">
    <p><a href="{{ route('tooling-maintenance.index') }}">← Back to register</a></p>

<a href="{{ route('tooling-maintenance.history', $record) }}">
    View complete tooling history →
</a>
    <p><a class="btn" href="{{ route('tooling-maintenance.edit', $record) }}">Edit record</a></p>

    @php
        $toolingItem = $record->pattern?->name
            ?? $record->patternRevision?->shortCode()
            ?? $record->coreDefinition?->name
            ?? $record->coreBox?->name
            ?? $record->mouldConfiguration?->name
            ?? $record->mouldAssembly?->assembly_number
            ?? 'Tooling item unavailable';
        $totalCost = (float) $record->parts_cost + (float) $record->other_cost;
        // The tool itself can carry photos and drawings (the files at the bottom belong to this job only).
        [$toolKey, $toolId] = match (true) {
            (bool) $record->pattern_id => ['pattern', $record->pattern_id],
            (bool) $record->patternRevision?->pattern_id => ['pattern', $record->patternRevision->pattern_id],
            (bool) $record->coreBox => ['core_box', $record->coreBox->id],
            (bool) $record->coreDefinition => ['core_definition', $record->coreDefinition->id],
            (bool) $record->mouldConfiguration => ['mould_configuration', $record->mouldConfiguration->id],
            default => [null, null],
        };
    @endphp

    <section class="panel">
        <h2>Tooling and status</h2>
        @if ($toolKey)@include('attachments._cover', ['type' => $toolKey, 'id' => $toolId, 'size' => 130, 'upload' => false])@endif
        <div class="grid">
            <div><div class="label">Tooling type</div><div class="value">{{ str_replace('_', ' ', $record->tooling_type) }}</div></div>
            <div><div class="label">Tooling item</div><div class="value">{{ $toolingItem }}</div></div>
            <div><div class="label">Activity</div><div class="value">{{ str_replace('_', ' ', $record->activity_type) }}</div></div>
            <div><div class="label">Priority</div><div class="value">{{ $record->priority }}</div></div>
            <div><div class="label">Status</div><div class="value">{{ $record->status }}</div></div>
            <div><div class="label">Reported at</div><div class="value">{{ $record->reported_at?->displayDateTime() ?? '—' }}</div></div>
            <div><div class="label">Reported by</div><div class="value">{{ $record->reported_by ?? '—' }}</div></div>
            <div><div class="label">Performed by</div><div class="value">{{ $record->performed_by ?? '—' }}</div></div>
        </div>
    </section>
    @if ($toolKey)@include('attachments._panel', ['type' => $toolKey, 'id' => $toolId])@endif

    <section class="panel">
        <h2>Maintenance details</h2>
        <div class="grid">
            @foreach ([
                'Problem / reason' => $record->problem_description,
                'Inspection findings' => $record->inspection_findings,
                'Work performed' => $record->work_performed,
                'Parts / materials used' => $record->parts_used,
                'Notes' => $record->notes,
            ] as $label => $value)
                <div><div class="label">{{ $label }}</div><div class="value">{{ $value ?: '—' }}</div></div>
            @endforeach
        </div>
    </section>

    <section class="panel">
        <h2>Costs, downtime and follow-up</h2>
        <div class="grid">
            <div><div class="label">Labour hours</div><div class="value">{{ \App\Support\Formats::number((float) $record->labour_hours, 2) }}</div></div>
            <div><div class="label">Downtime hours</div><div class="value">{{ \App\Support\Formats::number((float) $record->downtime_hours, 2) }}</div></div>
            <div><div class="label">Parts cost (PKR)</div><div class="value">{{ \App\Support\Formats::number((float) $record->parts_cost, 2) }}</div></div>
            <div><div class="label">Other cost (PKR)</div><div class="value">{{ \App\Support\Formats::number((float) $record->other_cost, 2) }}</div></div>
            <div><div class="label">Total cost (PKR)</div><div class="value">{{ \App\Support\Formats::number($totalCost, 2) }}</div></div>
            <div><div class="label">Next inspection due</div><div class="value">{{ $record->next_inspection_due_on?->displayDate() ?? '—' }}</div></div>
            <div><div class="label">Started at</div><div class="value">{{ $record->started_at?->displayDateTime() ?? '—' }}</div></div>
            <div><div class="label">Completed at</div><div class="value">{{ $record->completed_at?->displayDateTime() ?? '—' }}</div></div>
        </div>
    </section>

    @include('attachments._panel', ['type' => 'tooling_maintenance', 'id' => $record->id])
</div>
</div>
@endsection
