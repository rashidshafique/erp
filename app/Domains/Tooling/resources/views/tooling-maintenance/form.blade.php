@extends('layouts.app')

@section('title', "" . ( $editing ? 'Edit' : 'New' ) . " Maintenance")
@section('breadcrumb', "Maintenance")

@push('styles')
<style>
.legacy-page .panel { background: white; padding: 22px; border: 1px solid #dfe5eb; border-radius: 10px; }
.legacy-page .grid { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 16px; }
.legacy-page label { display: block; font-size: 13px; font-weight: 650; margin-bottom: 6px; }
.legacy-page input,
.legacy-page select,
.legacy-page textarea { width: 100%; padding: 10px; border: 1px solid #cbd3dd; border-radius: 6px; font: inherit; background: white; }
.legacy-page textarea { min-height: 90px; resize: vertical; }
.legacy-page .wide { grid-column: 1 / -1; }
.legacy-page .actions { display: flex; gap: 10px; margin-top: 20px; }
.legacy-page .btn { display: inline-block; padding: 10px 15px; border-radius: 6px; border: 1px solid var(--accent); background: var(--accent); color: white; text-decoration: none; cursor: pointer; font: inherit; }
.legacy-page .secondary { background: white; color: var(--accent); }
.legacy-page .error { color: #a12424; font-size: 13px; margin-top: 4px; }
.legacy-page .alert { padding: 12px; border-radius: 6px; background: #fff0dc; margin-bottom: 16px; }
.legacy-page h2 { margin: 24px 0 14px; font-size: 17px; }
@media(max-width:768px) {
.legacy-page .grid { grid-template-columns: 1fr; }
.legacy-page .wide { grid-column: auto; }
}
</style>
@endpush

@section('content')
@php
$oldValues = [
    'pattern_id' => old('pattern_id', $record->pattern_id),
    'pattern_revision_id' => old('pattern_revision_id', $record->pattern_revision_id),
    'core_definition_id' => old('core_definition_id', $record->core_definition_id),
    'core_box_id' => old('core_box_id', $record->core_box_id),
    'mould_configuration_id' => old('mould_configuration_id', $record->mould_configuration_id),
    'mould_assembly_id' => old('mould_assembly_id', $record->mould_assembly_id),
    'flask_id' => old('flask_id', $record->flask_id),
];
@endphp
<div class="legacy-page">
<div class="page-heading"><div class="legacy-header">
<h1>{{ $editing ? 'Edit Maintenance Record' : 'New Maintenance Record' }}</h1>
    <div>Foundry ERP · Tooling Maintenance</div>
</div></div>
<div class="legacy-main">
    <p><a href="{{ route('tooling-maintenance.index') }}">← Back to register</a></p>
    <section class="panel">

        <form method="POST" action="{{ $editing ? route('tooling-maintenance.update', $record) : route('tooling-maintenance.store') }}">
            @csrf
            @if ($editing) @method('PUT') @endif

            @if ($editing)
                <p><strong>Record:</strong> {{ $record->record_number }}</p>
            @endif

            <div class="grid">
                <div>
                    <label for="tooling_type">Tooling type *</label>
                    <select name="tooling_type" id="tooling_type" required>
                        <option value="">Select type</option>
                        @foreach ($toolingOptions as $type => $items)
                            <option value="{{ $type }}" @selected(old('tooling_type', $record->tooling_type) === $type)>
                                {{ str_replace('_', ' ', $type) }}
                            </option>
                        @endforeach
                    </select>
                    @error('tooling_type')<div class="error">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label for="tooling_item">Tooling item *</label>
                    <select id="tooling_item" required>
                        <option value="">Select a tooling item</option>
                    </select>
                </div>

                @foreach (['pattern_id','pattern_revision_id','core_definition_id','core_box_id','mould_configuration_id','mould_assembly_id','flask_id'] as $column)
                    <input type="hidden" name="{{ $column }}" id="{{ $column }}" value="{{ old($column, $record->$column) }}">
                @endforeach

                <div>
                    <label for="activity_type">Activity *</label>
                    <select name="activity_type" id="activity_type" required>
                        @foreach ($activities as $value)
                            <option value="{{ $value }}" @selected(old('activity_type', $record->activity_type ?: 'INSPECTION') === $value)>
                                {{ str_replace('_', ' ', $value) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="priority">Priority *</label>
                    <select name="priority" id="priority" required>
                        @foreach ($priorities as $value)
                            <option value="{{ $value }}" @selected(old('priority', $record->priority ?: 'NORMAL') === $value)>{{ $value }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="status">Status *</label>
                    <select name="status" id="status" required>
                        @foreach ($statuses as $value)
                            <option value="{{ $value }}" @selected(old('status', $record->status ?: 'OPEN') === $value)>{{ str_replace('_', ' ', $value) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="reported_at">Reported at</label>
                    <input type="datetime-local" name="reported_at" id="reported_at"
                           value="{{ old('reported_at', $record->reported_at?->format('Y-m-d\TH:i')) }}">
                </div>
                <div>
                    <label for="started_at">Work started</label>
                    <input type="datetime-local" name="started_at" id="started_at"
                           value="{{ old('started_at', $record->started_at?->format('Y-m-d\TH:i')) }}">
                </div>
                <div>
                    <label for="completed_at">Completed at</label>
                    <input type="datetime-local" name="completed_at" id="completed_at"
                           value="{{ old('completed_at', $record->completed_at?->format('Y-m-d\TH:i')) }}">
                </div>
                <div>
                    <label for="reported_by">Reported by</label>
                    <input name="reported_by" id="reported_by" maxlength="255" value="{{ old('reported_by', $record->reported_by) }}">
                </div>
                <div>
                    <label for="performed_by">Performed by</label>
                    <input name="performed_by" id="performed_by" maxlength="255" value="{{ old('performed_by', $record->performed_by) }}">
                </div>
                <div class="wide">
                    <label for="problem_description">Problem / reason for maintenance</label>
                    <textarea name="problem_description" id="problem_description">{{ old('problem_description', $record->problem_description) }}</textarea>
                </div>
                <div class="wide">
                    <label for="inspection_findings">Inspection findings</label>
                    <textarea name="inspection_findings" id="inspection_findings">{{ old('inspection_findings', $record->inspection_findings) }}</textarea>
                </div>
                <div class="wide">
                    <label for="work_performed">Work performed</label>
                    <textarea name="work_performed" id="work_performed">{{ old('work_performed', $record->work_performed) }}</textarea>
                </div>
                <div class="wide">
                    <label for="parts_used">Parts / materials used</label>
                    <textarea name="parts_used" id="parts_used">{{ old('parts_used', $record->parts_used) }}</textarea>
                </div>
                <div>
                    <label for="labour_hours">Labour hours</label>
                    <input type="number" min="0" step="0.01" name="labour_hours" id="labour_hours" value="{{ old('labour_hours', $record->labour_hours ?? 0) }}">
                </div>
                <div>
                    <label for="downtime_hours">Tooling downtime (hours)</label>
                    <input type="number" min="0" step="0.01" name="downtime_hours" id="downtime_hours" value="{{ old('downtime_hours', $record->downtime_hours ?? 0) }}">
                </div>
                <div>
                    <label for="parts_cost">Parts cost (PKR)</label>
                    <input type="number" min="0" step="0.01" name="parts_cost" id="parts_cost" value="{{ old('parts_cost', $record->parts_cost ?? 0) }}">
                </div>
                <div>
                    <label for="other_cost">Other cost (PKR)</label>
                    <input type="number" min="0" step="0.01" name="other_cost" id="other_cost" value="{{ old('other_cost', $record->other_cost ?? 0) }}">
                </div>
                <div>
                    <label for="next_inspection_due_on">Next inspection due</label>
                    <input type="date" name="next_inspection_due_on" id="next_inspection_due_on"
                           value="{{ old('next_inspection_due_on', $record->next_inspection_due_on?->format('Y-m-d')) }}">
                </div>
                <div class="wide">
                    <label for="notes">Notes</label>
                    <textarea name="notes" id="notes">{{ old('notes', $record->notes) }}</textarea>
                </div>
            </div>

            <div class="actions">
                <button class="btn" type="submit">{{ $editing ? 'Save changes' : 'Create maintenance record' }}</button>
                <a class="btn secondary" href="{{ route('tooling-maintenance.index') }}">Cancel</a>
            </div>
        </form>
    </section>
</div>

<script>
const options = @json($toolingOptions);
const columns = {
    PATTERN: 'pattern_id',
    PATTERN_REVISION: 'pattern_revision_id',
    CORE_DEFINITION: 'core_definition_id',
    CORE_BOX: 'core_box_id',
    MOULD_CONFIGURATION: 'mould_configuration_id',
    MOULD_ASSEMBLY: 'mould_assembly_id',
    FLASK: 'flask_id'
};
const typeSelect = document.getElementById('tooling_type');
const itemSelect = document.getElementById('tooling_item');
const oldValues = @json($oldValues);

function refreshTooling(selectedId = null) {
    const type = typeSelect.value;
    const column = columns[type];
    itemSelect.innerHTML = '<option value="">Select a tooling item</option>';

    Object.values(columns).forEach(name => {
        document.getElementById(name).value = '';
    });

    if (!column) return;

    (options[type] || []).forEach(item => {
        const option = document.createElement('option');
        option.value = item.id;
        option.textContent = item.label;
        if (String(item.id) === String(selectedId ?? oldValues[column] ?? '')) {
            option.selected = true;
        }
        itemSelect.appendChild(option);
    });

    document.getElementById(column).value = itemSelect.value;
}

typeSelect.addEventListener('change', () => refreshTooling(null));
itemSelect.addEventListener('change', () => {
    const column = columns[typeSelect.value];
    Object.values(columns).forEach(name => document.getElementById(name).value = '');
    if (column) document.getElementById(column).value = itemSelect.value;
});

refreshTooling(oldValues[columns[typeSelect.value]] ?? null);
</script>
</div>
@endsection
