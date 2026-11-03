@extends('layouts.app')

@section('title', 'Create Heat')
@section('breadcrumb')
<a href="{{ route('heats.index') }}">Heats</a> / New heat
@endsection

@section('content')
<div class="page-heading">
    <div>
        <h1>Create melting heat</h1>
        <p>Plan a melt and set its target metal weight and operating parameters.</p>
    </div>
    <a class="btn" href="{{ route('heats.index') }}">Back to heats</a>
</div>

<style>
    .hn-card { border: 1px solid #e6e7e9; border-radius: 8px; background: #fff; overflow: hidden; font-size: 13px; }
    .hn-grid { display: grid; grid-template-columns: minmax(0, 1fr) 420px; }
    .hn-main { padding: 20px 28px; display: flex; flex-direction: column; gap: 16px; min-width: 0; }
    .hn-main .panel { margin: 0; }
    .hn-side { border-left: 1px solid #e6e7e9; padding: 20px 24px; background: #f8f8f9; display: flex; flex-direction: column; gap: 12px; min-width: 0; }
    .hn-sec { font-size: 12px; font-weight: 600; color: #6b6e73; text-transform: uppercase; letter-spacing: .06em; }
    @media (max-width: 1000px) { .hn-grid { grid-template-columns: minmax(0, 1fr); } .hn-side { border-left: 0; border-top: 1px solid #e6e7e9; } }
</style>

@if($furnaces->isEmpty())
    <div class="panel"><div class="panel-body"><p>No active furnaces are available. Register or activate a furnace first.</p></div></div>
@else
<form method="POST" action="{{ route('heats.store') }}">
    @csrf

    <div class="hn-card"><div class="hn-grid">
    <div class="hn-main">
    <div class="panel">
        <div class="panel-header"><h2>Heat details</h2></div>
        <div class="panel-body">
            <div class="form-grid" data-progressive="3">
                <div class="field">
                    <label for="furnace_id">Furnace <span class="required">*</span></label>
                    <select id="furnace_id" name="furnace_id" required>
                        <option value="">Select furnace</option>
                        @foreach($furnaces as $furnace)
                            <option
                                value="{{ $furnace->id }}"
                                data-type="{{ $furnace->furnace_type }}"
                                data-max-power="{{ $furnace->max_operating_power_kw }}"
                                data-grade="{{ $furnace->default_iron_grade_id }}"
                                @selected(old('furnace_id', $default?->id) === $furnace->id)
                            >
                                {{ $furnace->code }} — {{ $furnace->name }} ({{ $furnace->furnace_type }})
                            </option>
                        @endforeach
                    </select>
                    @error('furnace_id')<div class="error-text">{{ $message }}</div>@enderror
                </div>

                <div class="field">
                    <label for="iron_grade_id">Iron grade</label>
                    <select id="iron_grade_id" name="iron_grade_id">
                        <option value="">No grade (no quality gates)</option>
                        @foreach($grades as $grade)
                            <option value="{{ $grade->id }}" @selected(old('iron_grade_id', old('furnace_id') ? null : $default?->default_iron_grade_id) === $grade->id)>{{ $grade->code }} ({{ $grade->family }})</option>
                        @endforeach
                    </select>
                    @error('iron_grade_id')<div class="error-text">{{ $message }}</div>@enderror
                </div>

                <div class="field">
                    <label for="charge_batch_id">Charge recipe</label>
                    <select id="charge_batch_id" name="charge_batch_id">
                        <option value="">No recipe selected</option>
                        @foreach($chargeBatches as $batch)
                            <option value="{{ $batch->id }}"
                                    data-process="{{ $batch->process_type }}"
                                    data-charge-weight="{{ $batch->target_total_charge_weight_kg }}"
                                    @selected(old('charge_batch_id') === $batch->id)>
                                {{ $batch->code }} — {{ $batch->name }} ({{ $batch->process_type }}, Rev {{ $batch->revision }})
                            </option>
                        @endforeach
                    </select>
                    @error('charge_batch_id')<div class="error-text">{{ $message }}</div>@enderror
                </div>

                <div class="field">
                    <label for="planned_start_at">Planned start</label>
                    <input id="planned_start_at" name="planned_start_at" type="datetime-local" value="{{ old('planned_start_at') }}">
                    @error('planned_start_at')<div class="error-text">{{ $message }}</div>@enderror
                </div>

                <div class="field">
                    <label for="target_metal_weight_kg">Target metal weight (kg)</label>
                    <input id="target_metal_weight_kg" name="target_metal_weight_kg" type="number" min="0" step="any" value="{{ old('target_metal_weight_kg') }}">
                    @error('target_metal_weight_kg')<div class="error-text">{{ $message }}</div>@enderror
                </div>

                <div class="field" id="power-limit-field">
                    <label for="operating_power_limit_kw">Heat operating power limit (kW)</label>
                    <input id="operating_power_limit_kw" name="operating_power_limit_kw" type="number" min="0" step="any" value="{{ old('operating_power_limit_kw') }}">
                    @error('operating_power_limit_kw')<div class="error-text">{{ $message }}</div>@enderror
                </div>

                <div class="field">
                    <label for="target_temperature_c">Target temperature (°C)</label>
                    <input id="target_temperature_c" name="target_temperature_c" type="number" min="0" step="any" value="{{ old('target_temperature_c') }}">
                    @error('target_temperature_c')<div class="error-text">{{ $message }}</div>@enderror
                </div>

                <div class="field">
                    <label for="operator_name">Responsible operator <span class="required">*</span></label>
                    <input id="operator_name" name="operator_name" maxlength="255" required value="{{ old('operator_name') }}">
                    @error('operator_name')<div class="error-text">{{ $message }}</div>@enderror
                </div>

                <div class="field field-full">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" rows="3" maxlength="10000">{{ old('notes') }}</textarea>
                </div>
            </div>
        </div>
    </div>

    <div id="started-block" class="panel" hidden>
        <div class="panel-body" style="display:grid;gap:8px">
            <label style="display:flex;gap:8px;align-items:center;font-weight:600"><input type="checkbox" id="already_started" name="already_started" value="1" style="width:auto" @checked(old('already_started'))>
                This heat has already started, at the time given as "Planned start"</label>
            <label id="fill-row" style="display:none;gap:8px;align-items:flex-start;margin-left:26px"><input type="checkbox" id="fill_charges" name="fill_charges" value="1" style="width:auto;margin-top:3px" @checked(old('fill_charges', true))>
                <span>Fill in the charges since the start from the recipe, each at its scheduled time. <strong id="fill-preview"></strong>
                    <span class="muted small" style="display:block">Marked as from the schedule; correct them on the Charge tab. Stock is not deducted.</span></span></label>
        </div>
    </div>

    <div class="actions">
        <button type="submit" class="btn btn-primary">Create heat</button>
        <a class="btn" href="{{ route('heats.index') }}">Cancel</a>
    </div>
    </div>

    <aside class="hn-side">
        <div class="hn-sec">Planned charge components</div>
        <div>
            <p id="recipe-preview-empty" class="muted">Choose a charge recipe to see its materials and planned quantities here.</p>
            <div id="recipe-preview" hidden>
                <p id="recipe-preview-basis" class="muted"></p>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr><th>Material</th><th>Role</th><th>Calculation</th><th>Planned kg</th><th>% of total</th></tr>
                        </thead>
                        <tbody id="recipe-preview-body"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </aside>
    </div></div>
</form>
@endif

<script>
document.addEventListener('DOMContentLoaded', function () {
    const furnace = document.getElementById('furnace_id');
    const field = document.getElementById('power-limit-field');
    const input = document.getElementById('operating_power_limit_kw');
    const recipe = document.getElementById('charge_batch_id');
    const recipePreview = document.getElementById('recipe-preview');
    const recipePreviewBody = document.getElementById('recipe-preview-body');
    const recipePreviewEmpty = document.getElementById('recipe-preview-empty');
    const recipePreviewBasis = document.getElementById('recipe-preview-basis');

    const recipeData = @json($recipeData);

    // A start in the past asks whether the heat has already started and, with a recipe, whether to fill in the charges since then.
    const startAt = document.getElementById('planned_start_at'), startedBlock = document.getElementById('started-block');
    const started = document.getElementById('already_started'), fillRow = document.getElementById('fill-row');
    const fillPreview = document.getElementById('fill-preview'), target = document.getElementById('target_metal_weight_kg');
    function pastStart() { return !!startAt.value && new Date(startAt.value) <= new Date(); }
    let previewTimer = null;
    function refreshStarted() {
        startedBlock.hidden = !pastStart();
        if (!pastStart()) { started.checked = false; }
        const withRecipe = !!recipe.value;
        fillRow.style.display = started.checked && withRecipe ? 'flex' : 'none';
        if (!(started.checked && withRecipe && furnace.value)) { fillPreview.textContent = ''; return; }
        clearTimeout(previewTimer);
        previewTimer = setTimeout(function () {
            const q = new URLSearchParams({ furnace_id: furnace.value, charge_batch_id: recipe.value, started_at: startAt.value, target_metal_weight_kg: target.value || '' });
            fetch(@json(route('heats.charge-preview')) + '?' + q, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (d) {
                    if (!d) { fillPreview.textContent = ''; return; }
                    fillPreview.textContent = d.reason ? d.reason : (d.count === 0 ? 'No charge is due yet.' : d.count + (d.count === 1 ? ' charge is' : ' charges are') + ' due since the start (one every ' + d.interval + ', ' + d.rate + ').');
                })
                .catch(function () { fillPreview.textContent = ''; });
        }, 300);
    }
    [startAt, started, furnace, recipe, target].forEach(function (el) { el.addEventListener('change', refreshStarted); });
    startAt.addEventListener('input', refreshStarted);
    refreshStarted();

    function updateRecipeOptions() {
        const option = furnace.options[furnace.selectedIndex];
        const process = option ? option.dataset.type : '';
        [...recipe.options].forEach(item => {
            if (!item.value) {
                item.hidden = false;
                return;
            }
            item.hidden = item.dataset.process !== process;
        });

        const selected = recipe.options[recipe.selectedIndex];
        if (selected && selected.value && selected.dataset.process !== process) {
            recipe.value = '';
        }
        updateRecipePreview();
    }

    function updateRecipePreview() {
        const selected = recipeData.find(item => item.id === recipe.value);
        recipePreviewBody.replaceChildren();

        recipePreviewEmpty.hidden = !!selected;
        if (!selected) {
            recipePreview.hidden = true;
            return;
        }

        recipePreview.hidden = false;
        const totalWeight = selected.weight !== null && selected.weight !== '' && Number(selected.weight) > 0
            ? Number(selected.weight)
            : null;
        recipePreviewBasis.textContent = totalWeight
            ? 'Total recipe charge: ' + totalWeight.toLocaleString(undefined, {maximumFractionDigits: 3}) + ' kg. Each material is shown in kg and as a share of this total.'
            : 'This percentage recipe has no target total charge weight, so component kg values cannot be calculated.';

        selected.items.forEach(item => {
            let calculation = '—';
            let quantity = null;
            let percentOfTotal = null;

            if (item.qty !== null && item.qty !== '') {
                quantity = Number(item.qty);
                calculation = 'Fixed quantity';
                if (totalWeight && totalWeight > 0) percentOfTotal = quantity / totalWeight * 100;
            } else if (item.percent !== null && item.percent !== '' && totalWeight) {
                percentOfTotal = Number(item.percent);
                quantity = totalWeight * percentOfTotal / 100;
                calculation = percentOfTotal.toFixed(window.decimalPlaces) + '% of charge';
            } else if (item.percent !== null && item.percent !== '') {
                percentOfTotal = Number(item.percent);
                calculation = percentOfTotal.toFixed(window.decimalPlaces) + '%; basis missing';
            }

            const tr = document.createElement('tr');
            [item.material || 'Unknown material', item.role || '—', calculation,
             quantity === null ? '—' : quantity.toLocaleString(undefined, {maximumFractionDigits: 3}) + ' kg',
             percentOfTotal === null ? '—' : percentOfTotal.toLocaleString(undefined, {maximumFractionDigits: 4}) + '%'
            ].forEach(value => {
                const td = document.createElement('td');
                td.textContent = value;
                tr.appendChild(td);
            });
            recipePreviewBody.appendChild(tr);
        });
    }

    function updatePowerLimit() {
        const option = furnace.options[furnace.selectedIndex];
        const isInduction = option && option.dataset.type === 'INDUCTION';
        field.hidden = !isInduction;
        input.disabled = !isInduction;

        if (!isInduction) {
            input.value = '';
            return;
        }
    }

    // The grade (or its iron family) suggests a temperature. It fills the field only while the field is empty or still holds the last suggestion.
    const grade = document.getElementById('iron_grade_id'), temperature = document.getElementById('target_temperature_c');
    const gradeTemps = @json($gradeTemps);
    let suggested = null;
    function updateSuggestedTemperature() {
        const s = gradeTemps[grade.value];
        if (!s) {
            if (suggested !== null && temperature.value === String(suggested)) { temperature.value = ''; }
            suggested = null;
            return;
        }
        if (temperature.value === '' || (suggested !== null && temperature.value === String(suggested))) { temperature.value = s.temp; }
        suggested = s.temp;
    }
    grade.addEventListener('change', updateSuggestedTemperature);
    updateSuggestedTemperature();

    furnace.addEventListener('change', function () {
        const preset = furnace.options[furnace.selectedIndex].dataset.grade;
        if (preset) { grade.value = preset; updateSuggestedTemperature(); }
        updatePowerLimit();
        updateRecipeOptions();
    });
    recipe.addEventListener('change', updateRecipePreview);
    updatePowerLimit();
    updateRecipeOptions();
});
</script>
@endsection
