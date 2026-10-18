
@extends('layouts.app')

@section('title', 'Heat '.$heat->heat_number)

@section('content')
<div class="page-heading">
    <div>
        <h1>Heat {{ $heat->heat_number }}</h1>
        <p>
            {{ $heat->furnace?->name ?? 'Unknown furnace' }}
            · {{ $heat->furnace?->furnace_type ?? $heat->process_type }}
            · {{ $heat->status }}
        </p>
    </div>
    <div class="actions">
        <a class="btn" href="{{ route('heats.index') }}">Back to heats</a>
    </div>
</div>

@if(session('success'))
    <div class="panel">
        <div class="panel-body" style="color:var(--success)">
            {{ session('success') }}
        </div>
    </div>
@endif

@if($errors->any())
    <div class="panel">
        <div class="panel-body" style="color:var(--danger)">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    </div>
@endif

<div class="tab-marker" data-tab-start="Overview"></div>
<div class="panel">
    <div class="panel-header"><h2>Heat summary</h2></div>
    <div class="panel-body" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:18px">
        <div>
            <div class="muted small">Target metal</div>
            <strong>
                {{ $heat->target_metal_weight_kg !== null
                    ? number_format((float) $heat->target_metal_weight_kg, 1).' kg'
                    : '—' }}
            </strong>
        </div>
        <div>
            <div class="muted small">Rated furnace power</div>
            <strong>
                {{ $heat->furnace?->rated_power_kw !== null
                    ? number_format((float) $heat->furnace->rated_power_kw, 1).' kW'
                    : '—' }}
            </strong>
        </div>
        <div>
            <div class="muted small">Furnace operating maximum</div>
            <strong>
                {{ $heat->furnace?->max_operating_power_kw !== null
                    ? number_format((float) $heat->furnace->max_operating_power_kw, 1).' kW'
                    : 'Not set' }}
            </strong>
        </div>
        <div>
            <div class="muted small">Heat operating limit</div>
            <strong>
                {{ $heat->operating_power_limit_kw !== null
                    ? number_format((float) $heat->operating_power_limit_kw, 1).' kW'
                    : 'Not set' }}
            </strong>
        </div>
    </div>
</div>

@include('heats._balance', ['heat' => $heat])
@if ($heat->isCupola())
    @include('heats._timeline', ['heat' => $heat])
@endif

<div class="tab-marker" data-tab-start="Chemistry & tests"></div>
@include('heats._sampling', ['heat' => $heat])
@include('heats._chemistry', ['heat' => $heat])
@include('heats._treatment', ['heat' => $heat])
@include('heats._tests', ['heat' => $heat])

<div class="tab-marker" data-tab-start="Charge"></div>
<div class="panel">
    <div class="panel-header"><h2>Charge recipe and planned materials</h2></div>
    <div class="panel-body">
        @if($heat->chargeBatch)
            <p>
                <strong>{{ $heat->chargeBatch->code }} — {{ $heat->chargeBatch->name }}</strong>
                · Revision {{ $heat->chargeBatch->revision }}
            </p>
            @if($heat->chargeBatch->instructions)
                <p>{{ $heat->chargeBatch->instructions }}</p>
            @endif
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>Material</th><th>Role</th><th>Recipe %</th><th>Planned kg</th></tr>
                    </thead>
                    <tbody>
                        @forelse($plannedChargeItems as $item)
                            <tr>
                                <td>{{ $item->material?->code }} — {{ $item->material?->name }}</td>
                                <td>{{ $item->component_role }}</td>
                                <td>{{ $item->target_percent !== null ? number_format((float) $item->target_percent, 4).'%' : '—' }}</td>
                                <td>
                                    {{ $item->planned_calculated_qty_kg !== null
                                        ? number_format((float) $item->planned_calculated_qty_kg, 3)
                                        : 'Basis required' }}
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="muted">This recipe has no components.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @php
                $actualByMaterial = $heat->materialConsumptions
                    ->groupBy('material_id')
                    ->map(fn ($entries) => (float) $entries->sum('actual_qty_kg'));

                $reconciliationRows = $plannedChargeItems
                    ->groupBy('material_id')
                    ->map(function ($items, $materialId) use ($actualByMaterial) {
                        $first = $items->first();
                        $planned = $items->contains(fn ($item) => $item->planned_calculated_qty_kg === null)
                            ? null
                            : (float) $items->sum('planned_calculated_qty_kg');

                        return [
                            'material_id' => $materialId,
                            'material' => $first->material,
                            'planned' => $planned,
                            'actual' => (float) ($actualByMaterial[$materialId] ?? 0),
                        ];
                    });

                $unplannedMaterialIds = $heat->materialConsumptions
                    ->pluck('material_id')
                    ->unique()
                    ->diff($reconciliationRows->keys());

                foreach ($unplannedMaterialIds as $materialId) {
                    $entry = $heat->materialConsumptions->firstWhere('material_id', $materialId);
                    $reconciliationRows->push([
                        'material_id' => $materialId,
                        'material' => $entry?->material,
                        'planned' => null,
                        'actual' => (float) $actualByMaterial[$materialId],
                    ]);
                }
            @endphp

            <h3 style="margin-top:24px">Planned vs actual charge</h3>
            <p class="muted small">
                Variance is actual minus planned. Positive means over plan; negative means under plan.
                Internal returns count as positive charge inputs. Materials not in the recipe are shown separately.
            </p>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Material</th>
                            <th>Planned kg</th>
                            <th>Actual kg</th>
                            <th>Variance kg</th>
                            <th>Variance %</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reconciliationRows as $row)
                            @php
                                $variance = $row['planned'] !== null
                                    ? $row['actual'] - $row['planned']
                                    : null;
                                $variancePercent = $row['planned'] !== null && $row['planned'] != 0
                                    ? ($variance / $row['planned']) * 100
                                    : null;
                            @endphp
                            <tr>
                                <td>
                                    {{ $row['material']?->code ?? 'Unknown' }}
                                    — {{ $row['material']?->name ?? 'Material unavailable' }}
                                    @if($row['planned'] === null)
                                        <span class="muted small">(No comparable plan)</span>
                                    @endif
                                </td>
                                <td>{{ $row['planned'] !== null ? number_format($row['planned'], 3) : '—' }}</td>
                                <td>{{ number_format($row['actual'], 3) }}</td>
                                <td>{{ $variance !== null ? number_format($variance, 3) : '—' }}</td>
                                <td>{{ $variancePercent !== null ? number_format($variancePercent, 2).'%' : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="muted">No recipe or actual charge entries available for comparison.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @else
            <p class="muted">No charge recipe is assigned to this heat.</p>
        @endif
    </div>
</div>


<div class="panel">
    <div class="panel-header">
        <h2>Actual charge and material costing</h2>
    </div>
    <div class="panel-body">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:18px;margin-bottom:20px">
            <div>
                <div class="muted small">Actual charge weight</div>
                <strong>{{ number_format($actualChargeQty, 3) }} kg</strong>
            </div>
            <div>
                <div class="muted small">Actual charge material cost</div>
                <strong>PKR {{ number_format($actualChargeCost, 2) }}</strong>
            </div>
            <div>
    <div class="muted small">Average charge material cost</div>
    <strong>
        {{ $actualChargeCostPerKg !== null
            ? 'PKR '.number_format($actualChargeCostPerKg, 2).'/kg'
            : '—' }}
    </strong>
</div>
<div>
    <div class="muted small">Charge posting status</div>
    <strong>{{ $chargeIsPosted ? 'Posted to inventory ledger' : 'Draft / not posted' }}</strong>
</div>
        </div>

        @if(!in_array($heat->status, ['CLOSED', 'CANCELLED'], true))
            <h3>Record charge material</h3>
            <p class="muted">
                Purchased materials use the cost recorded against an accepted lot.
                Internal returns require a positive transfer cost. Saving a charge does not deduct inventory.
            </p>

            <form method="POST" action="{{ route('heats.charge-entries.store', $heat) }}">
                @csrf
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:16px">
                    <div>
                        <label for="charge_consumption_type">Charge type *</label>
                        <select id="charge_consumption_type" name="consumption_type" required
                                style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                            <option value="RAW_MATERIAL" @selected(old('consumption_type', 'RAW_MATERIAL') === 'RAW_MATERIAL')>Purchased / raw material</option>
                            <option value="INTERNAL_RETURN" @selected(old('consumption_type') === 'INTERNAL_RETURN')>Internal return</option>
                        </select>
                    </div>
                    <div>
                        <label for="charge_material_id">Material *</label>
                        <select id="charge_material_id" name="material_id" required
                                style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                            <option value="">Select material</option>
                            @foreach($activeMaterials as $material)
                                <option value="{{ $material->id }}" @selected(old('material_id') === $material->id)>
                                    {{ $material->code }} — {{ $material->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div id="charge_lot_field">
                        <label for="charge_material_lot_id">Accepted material lot *</label>
                        <select id="charge_material_lot_id" name="material_lot_id"
                                style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                            <option value="">Select lot</option>
                            @foreach($acceptedLots as $lot)
                                <option value="{{ $lot->id }}"
                                        data-material-id="{{ $lot->material_id }}"
                                        @selected(old('material_lot_id') === $lot->id)>
                                    {{ $lot->lot_number }} — {{ $lot->material?->name }} (PKR {{ number_format((float) $lot->unit_cost, 4) }}/kg)
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="charge_actual_qty">Actual quantity (kg) *</label>
                        <input id="charge_actual_qty" name="actual_qty_kg" type="number"
                               min="0.001" step="0.001" required value="{{ old('actual_qty_kg') }}"
                               style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                    </div>
                    <div id="charge_transfer_cost_field" style="display:none">
                        <label for="charge_transfer_cost">Internal return transfer cost (PKR/kg) *</label>
                        <input id="charge_transfer_cost" name="transfer_cost_per_kg" type="number"
                               min="0.0001" step="0.0001" value="{{ old('transfer_cost_per_kg') }}"
                               style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                    </div>
                    <div>
                        <label for="charge_consumed_at">Charge date and time *</label>
                        <input id="charge_consumed_at" name="consumed_at" type="datetime-local" required
                               value="{{ old('consumed_at', now()->format('Y-m-d\TH:i')) }}"
                               style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                    </div>
                    <div>
                        <label for="charge_recorded_by">Recorded by *</label>
                        <input id="charge_recorded_by" name="recorded_by" required maxlength="100"
                               value="{{ old('recorded_by', $heat->operator_name) }}"
                               style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                    </div>
                    <div>
                        <label for="charge_source_reference">Source reference</label>
                        <input id="charge_source_reference" name="source_reference" maxlength="100"
                               value="{{ old('source_reference') }}"
                               placeholder="Return batch, supplier reference..."
                               style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                    </div>
                </div>
                <div style="margin-top:16px">
                    <label for="charge_notes">Notes</label>
                    <textarea id="charge_notes" name="notes" rows="2" maxlength="5000"
                              style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">{{ old('notes') }}</textarea>
                </div>
                <div class="actions" style="margin-top:16px">
                    <button class="btn btn-primary" type="submit">Record charge</button>
                </div>
            </form>
        @else
            <p class="muted">This heat is closed or cancelled; new charge entries are disabled.</p>
        @endif
    </div>
</div>

<div class="panel">
    <div class="panel-header">
        <h2>Actual charge history</h2>
        <span class="muted small">{{ $heat->materialConsumptions->count() }} entries</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th><th>Type</th><th>Material</th><th>Lot / reference</th>
                    <th>Quantity kg</th><th>Cost PKR/kg</th><th>Total cost PKR</th>
                    <th>Recorded at</th><th>Operator</th>
                </tr>
            </thead>
            <tbody>
                @forelse($heat->materialConsumptions as $entry)
                    @php
                        $entryUnitCost = $entry->actual_qty_kg > 0
                            ? (float) $entry->actual_total_cost / (float) $entry->actual_qty_kg
                            : null;
                    @endphp
                    <tr>
                        <td>{{ $entry->sequence }}</td>
                        <td>{{ str_replace('_', ' ', $entry->consumption_type) }}</td>
                        <td>{{ $entry->material?->code }} — {{ $entry->material?->name }}</td>
                        <td>{{ $entry->materialLot?->lot_number ?? $entry->source_reference ?? '—' }}</td>
                        <td>{{ number_format((float) $entry->actual_qty_kg, 3) }}</td>
                        <td>{{ $entryUnitCost !== null ? number_format($entryUnitCost, 4) : '—' }}</td>
                        <td>{{ number_format((float) $entry->actual_total_cost, 2) }}</td>
                        <td>{{ $entry->consumed_at?->format('d M Y, H:i') ?? '—' }}</td>
                        <td>{{ $entry->recorded_by }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="muted">No actual charge entries recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@if(!in_array($heat->status, ['CLOSED', 'CANCELLED'], true))
    <div class="panel">
        <div class="panel-header">
            <h2>Post charge to inventory</h2>
        </div>
        <div class="panel-body">
            @if($chargeIsPosted)
                <p style="color:var(--success)">
                    This heat's charge has already been posted.
                    Inventory deductions must not be repeated.
                </p>
                <p>
                    <strong>Posted charge cost:</strong>
                    PKR {{ number_format($actualChargeCost, 2) }}
                </p>
            @elseif($heat->materialConsumptions->isEmpty())
                <p class="muted">
                    Record the actual charge materials before posting.
                </p>
            @else
                <p>
                    Posting will deduct purchased materials from their selected
                    lots using FIFO cost layers and create inventory movements.
                    Internal returns will be costed but will not be deducted
                    from purchased-material inventory.
                </p>
                <p class="muted">
                    Verify the quantities, lots and transfer costs in the
                    charge history before proceeding. Posting should be
                    treated as an inventory transaction, not a draft save.
                </p>

                <form method="POST"
                      action="{{ route('heats.charge-postings.store', $heat) }}"
                      onsubmit="return confirm('Post this heat charge to inventory? This action deducts purchased-material stock and must not be repeated.');">
                    @csrf

                    <div style="margin-bottom:16px">
                        <label for="charge_posting_notes">Posting notes</label>
                        <textarea
                            id="charge_posting_notes"
                            name="notes"
                            rows="2"
                            maxlength="5000"
                            style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit"
                        >{{ old('notes') }}</textarea>
                    </div>

                    <button class="btn btn-primary" type="submit">
                        Post charge to inventory
                    </button>
                </form>
            @endif
        </div>
    </div>
@endif
<script>
document.addEventListener('DOMContentLoaded', function () {
    const type = document.getElementById('charge_consumption_type');
    const material = document.getElementById('charge_material_id');
    const lot = document.getElementById('charge_material_lot_id');
    const lotField = document.getElementById('charge_lot_field');
    const transferField = document.getElementById('charge_transfer_cost_field');
    const transferCost = document.getElementById('charge_transfer_cost');

    function updateChargeFields() {
        const isReturn = type.value === 'INTERNAL_RETURN';
        lotField.style.display = isReturn ? 'none' : '';
        lot.disabled = isReturn;
        lot.required = !isReturn;
        transferField.style.display = isReturn ? '' : 'none';
        transferCost.required = isReturn;
        transferCost.disabled = !isReturn;

        Array.from(lot.options).forEach(function (option) {
            if (!option.value) return;
            option.hidden = material.value !== '' && option.dataset.materialId !== material.value;
        });

        const selected = lot.options[lot.selectedIndex];
        if (selected && selected.value && selected.hidden) lot.value = '';
    }

    type.addEventListener('change', updateChargeFields);
    material.addEventListener('change', updateChargeFields);
    updateChargeFields();
});
</script>

<div class="tab-marker" data-tab-start="Furnace readings"></div>
@if($heat->furnace?->furnace_type === 'INDUCTION')
    <div class="panel">
        <div class="panel-header"><h2>Record induction meter reading</h2></div>
        <div class="panel-body">
            <p class="muted">
                Enter the values shown on the meter. At least one meter value is required.
                Cumulative kWh is the meter total, not the energy used in this interval.
            </p>
            <form method="POST" action="{{ route('heats.power-readings.store', $heat) }}">
                @csrf
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:18px">
                    <div>
                        <label for="power_recorded_at">Reading date and time *</label>
                        <input id="power_recorded_at" name="recorded_at" type="datetime-local"
                               required value="{{ old('recorded_at', now()->format('Y-m-d\TH:i')) }}"
                               style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                    </div>
                    <div>
                        <label for="actual_power_kw">Actual power (kW)</label>
                        <input id="actual_power_kw" name="actual_power_kw" type="number"
                               min="0" step="0.001" value="{{ old('actual_power_kw') }}"
                               style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                    </div>
                    <div>
                        <label for="cumulative_energy_kwh">Cumulative energy (kWh)</label>
                        <input id="cumulative_energy_kwh" name="cumulative_energy_kwh" type="number"
                               min="0" step="0.001" value="{{ old('cumulative_energy_kwh') }}"
                               style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                    </div>
                    <div>
                        <label for="power_recorded_by">Operator name *</label>
                        <input id="power_recorded_by" name="recorded_by" required maxlength="100"
                               value="{{ old('recorded_by', $heat->operator_name) }}"
                               style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:16px;margin-bottom:16px">
    <div>
        <label for="charge_posted_by">Posted by *</label>
        <input
            id="charge_posted_by"
            name="posted_by"
            required
            maxlength="100"
            value="{{ old('posted_by', $heat->operator_name) }}"
            style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit"
        >
    </div>

    <div>
        <label for="charge_posting_notes">Posting notes</label>
        <textarea
            id="charge_posting_notes"
            name="notes"
            rows="2"
            maxlength="2000"
            style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit"
        >{{ old('notes') }}</textarea>
    </div>
</div>
                <div class="actions" style="margin-top:18px">
                    <button class="btn btn-primary" type="submit">Save power reading</button>
                </div>
            </form>
        </div>
    </div>
@endif

@if($heat->furnace?->furnace_type === 'CUPOLA')
    <div class="panel">
        <div class="panel-header"><h2>Record cupola process reading</h2></div>
        <div class="panel-body">
            <p class="muted">
                Use a consistent parameter code and unit for repeat readings.
                Examples might include blast pressure or temperature, but enter only parameters
                you actually measure at your furnace.
            </p>
            <form method="POST" action="{{ route('heats.process-readings.store', $heat) }}">
                @csrf
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:18px">
                    <div>
                        <label for="process_recorded_at">Reading date and time *</label>
                        <input id="process_recorded_at" name="recorded_at" type="datetime-local"
                               required value="{{ old('recorded_at', now()->format('Y-m-d\TH:i')) }}"
                               style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                    </div>
                    <div>
                        <label for="parameter_code">Parameter code *</label>
                        <input id="parameter_code" name="parameter_code" required maxlength="60"
                               pattern="[A-Za-z0-9_.-]+"
                               placeholder="e.g. BLAST_PRESSURE"
                               value="{{ old('parameter_code') }}"
                               style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                    </div>
                    <div>
                        <label for="numeric_value">Value *</label>
                        <input id="numeric_value" name="numeric_value" type="number" required
                               step="any" value="{{ old('numeric_value') }}"
                               style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                    </div>
                    <div>
                        <label for="unit">Unit *</label>
                        <input id="unit" name="unit" required maxlength="30"
                               placeholder="e.g. mbar, °C, Hz"
                               value="{{ old('unit') }}"
                               style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                    </div>
                    <div>
                        <label for="process_recorded_by">Operator name *</label>
                        <input id="process_recorded_by" name="recorded_by" required maxlength="100"
                               value="{{ old('recorded_by', $heat->operator_name) }}"
                               style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                    </div>
                </div>
                <div style="margin-top:18px">
                    <label for="process_notes">Notes</label>
                    <textarea id="process_notes" name="notes" rows="2" maxlength="5000"
                              style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">{{ old('notes') }}</textarea>
                </div>
                <div class="actions" style="margin-top:18px">
                    <button class="btn btn-primary" type="submit">Save process reading</button>
                </div>
            </form>
        </div>
    </div>
@endif

@if($heat->furnace?->furnace_type === 'INDUCTION')
    <div class="panel">
        <div class="panel-header">
            <h2>Power reading history</h2>
            <span class="muted small">{{ $heat->powerReadings->count() }} readings</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Recorded at</th>
                        <th>Actual power</th>
                        <th>Cumulative energy</th>
                        <th>Operator</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($heat->powerReadings as $reading)
                        <tr>
                            <td>{{ $reading->reading_number }}</td>
                            <td>{{ $reading->recorded_at?->format('d M Y, H:i') }}</td>
                            <td>
                                {{ $reading->actual_power_kw !== null
                                    ? number_format((float) $reading->actual_power_kw, 3).' kW'
                                    : '—' }}
                            </td>
                            <td>
                                {{ $reading->cumulative_energy_kwh !== null
                                    ? number_format((float) $reading->cumulative_energy_kwh, 3).' kWh'
                                    : '—' }}
                            </td>
                            <td>{{ $reading->recorded_by }}</td>
                            <td>{{ $reading->notes ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="muted">No power readings recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endif

@if($heat->furnace?->furnace_type === 'CUPOLA')
    <div class="panel">
        <div class="panel-header">
            <h2>Process reading history</h2>
            <span class="muted small">{{ $heat->processReadings->count() }} readings</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Recorded at</th>
                        <th>Parameter</th>
                        <th>Value</th>
                        <th>Operator</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($heat->processReadings as $reading)
                        <tr>
                            <td>{{ $reading->reading_number }}</td>
                            <td>{{ $reading->recorded_at?->format('d M Y, H:i') }}</td>
                            <td>{{ $reading->parameter_code }}</td>
                            <td>{{ $reading->numeric_value }} {{ $reading->unit }}</td>
                            <td>{{ $reading->recorded_by }}</td>
                            <td>{{ $reading->notes ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="muted">No process readings recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endif
<div class="tab-marker" data-tab-start="Files & history"></div>
@include('custom-fields._panel', ['entity' => 'heat', 'id' => $heat->id])
@include('attachments._panel', ['type' => 'heat', 'id' => $heat->id])
@include('audit._history', ['type' => 'Heat', 'id' => $heat->id])
@endsection
