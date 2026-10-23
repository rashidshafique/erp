@extends('layouts.app')

@section('title', 'Create Heat')

@section('content')
<div class="page-heading">
    <div>
        <h1>Create melting heat</h1>
        <p>Plan a melt and set its target metal weight and operating parameters.</p>
    </div>
    <div class="actions">
        <a class="btn" href="{{ route('heats.index') }}">Back to heats</a>
    </div>
</div>


<div class="panel">
    <div class="panel-header"><h2>Heat details</h2></div>
    <div class="panel-body">
        @if($furnaces->isEmpty())
            <p>No active furnaces are available. Register or activate a furnace first.</p>
        @else
        <form method="POST" action="{{ route('heats.store') }}">
            @csrf

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:18px">
                <div>
                    <label for="furnace_id">Furnace *</label>
                    <select id="furnace_id" name="furnace_id" required
                            style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                        <option value="">Select furnace</option>
                        @foreach($furnaces as $furnace)
                            <option
                                value="{{ $furnace->id }}"
                                data-type="{{ $furnace->furnace_type }}"
                                data-max-power="{{ $furnace->max_operating_power_kw }}"
                                @selected(old('furnace_id') === $furnace->id)
                            >
                                {{ $furnace->code }} — {{ $furnace->name }} ({{ $furnace->furnace_type }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="iron_grade_id">Iron grade</label>
                    <select id="iron_grade_id" name="iron_grade_id"
                            style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                        <option value="">No grade (no quality gates)</option>
                        @foreach($grades as $grade)
                            <option value="{{ $grade->id }}" @selected(old('iron_grade_id') === $grade->id)>{{ $grade->code }} ({{ $grade->family }})</option>
                        @endforeach
                    </select>
                    @error('iron_grade_id')<div class="error-text">{{ $message }}</div>@enderror
                </div>

                <div>
                    <label for="charge_batch_id">Charge recipe</label>
                    <select id="charge_batch_id" name="charge_batch_id"
                            style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
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
                    <small class="muted">Only active, currently effective recipes matching the furnace are selectable.</small>
                </div>

                <div>
                    <label for="planned_start_at">Planned start</label>
                    <input id="planned_start_at" name="planned_start_at" type="datetime-local"
                           value="{{ old('planned_start_at') }}"
                           style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                </div>

                <div>
                    <label for="target_metal_weight_kg">Target metal weight (kg)</label>
                    <input id="target_metal_weight_kg" name="target_metal_weight_kg"
                           type="number" min="0.001" step="0.001"
                           value="{{ old('target_metal_weight_kg') }}"
                           style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                    <small class="muted">Optional. Set it later on the heat if it is not known yet.</small>
                </div>

                <div id="power-limit-field">
                    <label for="operating_power_limit_kw">Heat operating power limit (kW)</label>
                    <input id="operating_power_limit_kw" name="operating_power_limit_kw"
                           type="number" min="0.001" step="0.001"
                           value="{{ old('operating_power_limit_kw') }}"
                           style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                    <small id="power-limit-help" class="muted">
                        Applies to induction heats only.
                    </small>
                </div>

                <div>
                    <label for="target_temperature_c">Target temperature (°C)</label>
                    <input id="target_temperature_c" name="target_temperature_c"
                           type="number" min="0.001" step="0.01"
                           value="{{ old('target_temperature_c') }}"
                           style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                    <small class="muted" id="temperature-hint">Optional. Choose an iron grade to see a suggested temperature.</small>
                </div>

                <div>
                    <label for="operator_name">Responsible operator *</label>
                    <input id="operator_name" name="operator_name" maxlength="255" required
                           value="{{ old('operator_name') }}"
                           style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                </div>
            </div>

            <div id="started-block" class="panel" style="margin-top:18px" hidden>
                <div class="panel-body" style="display:grid;gap:8px">
                    <label style="display:flex;gap:8px;align-items:center;font-weight:600"><input type="checkbox" id="already_started" name="already_started" value="1" style="width:auto" @checked(old('already_started'))>
                        This heat has already started, at the time given as "Planned start"</label>
                    <label id="fill-row" style="display:none;gap:8px;align-items:flex-start;margin-left:26px"><input type="checkbox" id="fill_charges" name="fill_charges" value="1" style="width:auto;margin-top:3px" @checked(old('fill_charges', true))>
                        <span>Fill in the charges since the start from the recipe, each at its scheduled time. <strong id="fill-preview"></strong>
                            <span class="muted small" style="display:block">They are marked as filled in from the schedule, and can be corrected on the Charge tab. Inventory is not deducted.</span></span></label>
                </div>
            </div>

            <div id="recipe-preview" class="panel" style="margin-top:18px" hidden>
                <div class="panel-header"><h2>Planned charge components</h2></div>
                <div class="panel-body">
                    <p id="recipe-preview-basis" class="muted"></p>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr><th>Material</th><th>Role</th><th>Calculation</th><th>Planned kg</th><th>% of total</th></tr>
                            </thead>
                            <tbody id="recipe-preview-body"></tbody>
                        </table>
                    </div>
                    <p class="muted">These are planned quantities only. Actual charge entries and costs will be recorded separately.</p>
                </div>
            </div>

            <div style="margin-top:18px">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes" rows="3" maxlength="10000"
                          style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">{{ old('notes') }}</textarea>
            </div>

            <div class="actions" style="margin-top:22px">
                <button type="submit" class="btn btn-primary">Create heat</button>
                <a class="btn" href="{{ route('heats.index') }}">Cancel</a>
            </div>
        </form>
        @endif
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const furnace = document.getElementById('furnace_id');
    const field = document.getElementById('power-limit-field');
    const input = document.getElementById('operating_power_limit_kw');
    const help = document.getElementById('power-limit-help');
    const recipe = document.getElementById('charge_batch_id');
    const recipePreview = document.getElementById('recipe-preview');
    const recipePreviewBody = document.getElementById('recipe-preview-body');
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
                calculation = percentOfTotal.toFixed(4) + '% of charge';
            } else if (item.percent !== null && item.percent !== '') {
                percentOfTotal = Number(item.percent);
                calculation = percentOfTotal.toFixed(4) + '%; basis missing';
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
            help.textContent = 'Applies to induction heats only.';
            return;
        }

        const maximum = option.dataset.maxPower;
        help.textContent = maximum
            ? 'Furnace maximum configured: ' + maximum + ' kW.'
            : 'No furnace maximum configured. Enter a limit only if known.';
    }

    // The grade (or its iron family) suggests a temperature. It fills the field only while the field is empty or still holds the last suggestion.
    const grade = document.getElementById('iron_grade_id'), temperature = document.getElementById('target_temperature_c');
    const temperatureHint = document.getElementById('temperature-hint'), gradeTemps = @json($gradeTemps);
    let suggested = null;
    function updateSuggestedTemperature() {
        const s = gradeTemps[grade.value];
        if (!s) {
            if (suggested !== null && temperature.value === String(suggested)) { temperature.value = ''; }
            suggested = null;
            temperatureHint.textContent = 'Optional. Choose an iron grade to see a suggested temperature.';
            return;
        }
        if (temperature.value === '' || (suggested !== null && temperature.value === String(suggested))) { temperature.value = s.temp; }
        suggested = s.temp;
        temperatureHint.textContent = 'Suggested ' + s.temp + ' °C, ' + s.source + '. Change it if the melt needs something else.';
    }
    grade.addEventListener('change', updateSuggestedTemperature);
    updateSuggestedTemperature();

    furnace.addEventListener('change', function () {
        updatePowerLimit();
        updateRecipeOptions();
    });
    recipe.addEventListener('change', updateRecipePreview);
    updatePowerLimit();
    updateRecipeOptions();
});
</script>
@endsection
