@extends('layouts.app')

@section('title', "" . ( $editing ? 'Edit' : 'Configure' ) . " Tooling Life")
@section('breadcrumb', "Tooling Life")

@push('styles')
<style>
.legacy-page .legacy-header h1 { margin: 0 0 4px; font-size: 24px; }
.legacy-page .legacy-header p { margin: 0; color: #c6d2dc; }
.legacy-page .panel { background: white; border: 1px solid #dce3e9; border-radius: 10px; padding: 24px; }
.legacy-page .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
.legacy-page .field { margin-bottom: 18px; }
.legacy-page label { display: block; font-weight: 600; margin-bottom: 6px; }
.legacy-page input,
.legacy-page select,
.legacy-page textarea { width: 100%; padding: 10px 11px; border: 1px solid #ccd4d9; border-radius: 6px; background: white; color: var(--text); font: inherit; }
.legacy-page textarea { min-height: 90px; resize: vertical; }
.legacy-page small,
.legacy-page .hint { display: block; color: var(--muted); margin-top: 5px; }
.legacy-page .error { color: #b42318; margin-top: 5px; }
.legacy-page .actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 22px; }
.legacy-page .btn { display: inline-block; padding: 10px 15px; border: 0; border-radius: 6px; font: inherit; font-weight: 600; text-decoration: none; cursor: pointer; }
.legacy-page .primary { background: var(--accent); color: white; }
.legacy-page .secondary { background: #eef1f4; color: var(--text); }
.legacy-page .notice { background: #fff7e6; border: 1px solid #f0d69b; border-radius: 6px; padding: 12px; margin-bottom: 18px; }
.legacy-page .check { display: flex; align-items: center; gap: 9px; }
.legacy-page .check input { width: auto; }
@media (max-width: 768px) {
.legacy-page .grid { grid-template-columns: 1fr; gap: 0; }
.legacy-page .panel { padding: 17px; }
}
</style>
@endpush

@section('content')
<div class="legacy-page">
<div class="page-heading"><div class="legacy-header">
<h1>{{ $editing ? 'Edit Tooling Life Limit' : 'Configure Tooling Life' }}</h1>
    <p>Set expected service life and preventive inspection intervals.</p>
</div></div>

<div class="legacy-main">
    <div style="margin-bottom:16px">
        <a href="{{ route('tooling-life.dashboard') }}">← Back to tooling life dashboard</a>
    </div>

    <section class="panel">

        <form method="POST"
              action="{{ $editing ? route('tooling-life.update', $limit) : route('tooling-life.store') }}">
            @csrf
            @if ($editing)
                @method('PUT')
            @endif

            @php
                $currentType = old('tooling_type', $limit->tooling_type);
                $currentToolingId = old('tooling_id');

                if ($editing && !$currentToolingId) {
                    $foreignKeys = [
                        'PATTERN' => 'pattern_id',
                        'PATTERN_REVISION' => 'pattern_revision_id',
                        'CORE_DEFINITION' => 'core_definition_id',
                        'CORE_BOX' => 'core_box_id',
                        'MOULD_CONFIGURATION' => 'mould_configuration_id',
                        'MOULD_ASSEMBLY' => 'mould_assembly_id',
                        'FLASK' => 'flask_id',
                    ];

                    $currentToolingId = $limit->{$foreignKeys[$currentType] ?? 'pattern_id'};
                }
            @endphp

            <div class="grid">
                <div class="field">
                    <label for="tooling_type">Tooling type *</label>
                    <select id="tooling_type" name="tooling_type" required {{ $editing ? 'disabled' : '' }}>
                        @foreach ($toolingOptions as $type => $options)
                            <option value="{{ $type }}" @selected($currentType === $type)>
                                {{ str_replace('_', ' ', $type) }}
                            </option>
                        @endforeach
                    </select>
                    @if ($editing)
                        <input type="hidden" name="tooling_type" value="{{ $currentType }}">
                    @endif
                    @error('tooling_type') <div class="error">{{ $message }}</div> @enderror
                </div>

                <div class="field">
                    <label for="tooling_id">Tooling item *</label>
                    <select id="tooling_id" name="tooling_id" required {{ $editing ? 'disabled' : '' }}>
                        @foreach ($toolingOptions as $type => $options)
                            @foreach ($options as $option)
                                <option value="{{ $option['id'] }}"
                                        data-type="{{ $type }}"
                                        @selected((string) $currentToolingId === (string) $option['id'])>
                                    {{ $option['label'] }}
                                </option>
                            @endforeach
                        @endforeach
                    </select>
                    @if ($editing)
                        <input type="hidden" name="tooling_id" value="{{ $currentToolingId }}">
                        <small>The tooling item cannot be changed after this configuration is created.</small>
                    @else
                        <small>Choose the specific pattern, core box, or other tooling item.</small>
                    @endif
                    @error('tooling_id') <div class="error">{{ $message }}</div> @enderror
                </div>

                <div class="field">
                    <label for="usage_unit">Usage unit *</label>
                    <select id="usage_unit" name="usage_unit" required>
                        @foreach (['CYCLES', 'MOULDS', 'CORES', 'HOURS', 'SHOTS', 'PIECES'] as $unit)
                            <option value="{{ $unit }}" @selected(old('usage_unit', $limit->usage_unit ?? 'CYCLES') === $unit)>
                                {{ $unit }}
                            </option>
                        @endforeach
                    </select>
                    @error('usage_unit') <div class="error">{{ $message }}</div> @enderror
                </div>

                <div class="field">
                    <label for="service_life_quantity">Expected service life</label>
                    <input id="service_life_quantity" name="service_life_quantity" type="number"
                           min="0" step="any"
                           value="{{ old('service_life_quantity', $limit->service_life_quantity) }}"
                           placeholder="e.g. 50000">
                    <small>Leave blank if no service-life limit is defined.</small>
                    @error('service_life_quantity') <div class="error">{{ $message }}</div> @enderror
                </div>

                <div class="field">
                    <label for="inspection_interval_quantity">Inspection interval</label>
                    <input id="inspection_interval_quantity" name="inspection_interval_quantity" type="number"
                           min="0" step="any"
                           value="{{ old('inspection_interval_quantity', $limit->inspection_interval_quantity) }}"
                           placeholder="e.g. 5000">
                    <small>Usage units between inspections.</small>
                    @error('inspection_interval_quantity') <div class="error">{{ $message }}</div> @enderror
                </div>

                <div class="field">
                    <label for="warning_threshold_percent">Warning threshold (%) *</label>
                    <input id="warning_threshold_percent" name="warning_threshold_percent" type="number"
                           min="1" max="100" step="1" required
                           value="{{ old('warning_threshold_percent', $limit->warning_threshold_percent ?? 80) }}">
                    <small>Show a warning when either limit reaches this percentage.</small>
                    @error('warning_threshold_percent') <div class="error">{{ $message }}</div> @enderror
                </div>

                <div class="field">
                    <label for="usage_at_last_inspection">Cumulative usage at last inspection</label>
                    <input id="usage_at_last_inspection" name="usage_at_last_inspection" type="number"
                           min="0" step="any"
                           value="{{ old('usage_at_last_inspection', $limit->usage_at_last_inspection ?? 0) }}">
                    <small>Enter the lifetime usage counter at the last inspection, not just the interval usage.</small>
                    @error('usage_at_last_inspection') <div class="error">{{ $message }}</div> @enderror
                </div>

                <div class="field">
                    <label for="last_inspected_at">Last inspection date and time</label>
                    <input id="last_inspected_at" name="last_inspected_at" type="datetime-local"
                           value="{{ old('last_inspected_at', $limit->last_inspected_at ? $limit->last_inspected_at->format('Y-m-d\TH:i') : '') }}">
                    @error('last_inspected_at') <div class="error">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="field">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes" maxlength="10000"
                          placeholder="Inspection criteria, known wear points, repair guidance...">{{ old('notes', $limit->notes) }}</textarea>
                @error('notes') <div class="error">{{ $message }}</div> @enderror
            </div>

            <div class="field">
                <label class="check">
                    <input type="checkbox" name="is_active" value="1"
                           @checked(old('is_active', $limit->is_active ?? true))>
                    Active — include this tooling item on the life dashboard
                </label>
            </div>

            <div class="actions">
                <button class="btn primary" type="submit">
                    {{ $editing ? 'Save changes' : 'Create life limit' }}
                </button>
                <a class="btn secondary" href="{{ route('tooling-life.dashboard') }}">Cancel</a>
            </div>
        </form>
    </section>
</div>

<script>
(() => {
    const typeSelect = document.getElementById('tooling_type');
    const itemSelect = document.getElementById('tooling_id');

    if (!typeSelect || !itemSelect || typeSelect.disabled || itemSelect.disabled) {
        return;
    }

    const filterItems = (preserveSelection = false) => {
        const selectedType = typeSelect.value;
        let firstVisible = null;
        let selectedVisible = false;

        Array.from(itemSelect.options).forEach(option => {
            const visible = option.dataset.type === selectedType;
            option.hidden = !visible;
            option.disabled = !visible;

            if (visible && !firstVisible) firstVisible = option;
            if (visible && option.selected) selectedVisible = true;
        });

        if (!preserveSelection || !selectedVisible) {
            itemSelect.value = firstVisible ? firstVisible.value : '';
        }
    };

    typeSelect.addEventListener('change', () => filterItems());
    filterItems(true);
})();
</script>
</div>
@endsection
