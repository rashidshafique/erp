@php
    $editing = $chargeBatch->exists;
    $value = fn (string $field, $default = null) => old($field, $editing ? $chargeBatch->{$field} : $default);
    $date = fn (string $field) => old($field, $chargeBatch->{$field}?->format('Y-m-d'));
    $roles = \App\Models\ChargeBatch::ROLES;
    $additions = \App\Models\ChargeBatch::NON_METAL_ROLES;
    $rowRoles = fn (string $current) => array_key_exists($current, $roles) || $current === '' ? $roles : $roles + [$current => $current];
@endphp
<form method="POST" action="{{ $editing ? route('charge-batches.update', $chargeBatch) : route('charge-batches.store') }}" id="recipe-form">
    @csrf
    @if ($editing) @method('PUT') @endif

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px">
        <div>
            <label for="code">Recipe code *</label>
            <input id="code" name="code" class="form-control" required value="{{ $value('code') }}">
        </div>
        <div>
            <label for="name">Recipe name *</label>
            <input id="name" name="name" class="form-control" required value="{{ $value('name') }}">
        </div>
        <div>
            <label for="process_type">Process *</label>
            <select id="process_type" name="process_type" class="form-control" required>
                @foreach(['CUPOLA','INDUCTION','OTHER'] as $process)
                    <option value="{{ $process }}" @selected($value('process_type', 'CUPOLA') === $process)>{{ $process }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="status">Status *</label>
            <select id="status" name="status" class="form-control" required>
                @foreach(['DRAFT','ACTIVE','INACTIVE'] as $status)
                    <option value="{{ $status }}" @selected($value('status', 'DRAFT') === $status)>{{ $status }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="revision">Revision *</label>
            <input id="revision" name="revision" class="form-control" required value="{{ $value('revision', 'A') }}">
        </div>
        <div>
            <label for="target_metal_weight_kg">Target tapped metal (kg)</label>
            <input id="target_metal_weight_kg" name="target_metal_weight_kg" type="number" min="0" step="any" class="form-control" value="{{ $value('target_metal_weight_kg') }}">
        </div>
        <div>
            <label for="target_total_charge_weight_kg">Target total charge (kg)</label>
            <input id="target_total_charge_weight_kg" name="target_total_charge_weight_kg" type="number" min="0" step="any" class="form-control" value="{{ $value('target_total_charge_weight_kg') }}">
            <small class="muted" id="charge-weight-help">Required for percentage recipes. Fixed-kg recipes calculate this total automatically.</small>
        </div>
        <div>
            <label for="effective_from">Effective from</label>
            <input id="effective_from" name="effective_from" type="date" class="form-control" value="{{ $date('effective_from') }}">
        </div>
        <div>
            <label for="effective_to">Effective to</label>
            <input id="effective_to" name="effective_to" type="date" class="form-control" value="{{ $date('effective_to') }}">
        </div>
    </div>

    <div style="margin-top:16px">
        <label for="instructions">Operating instructions</label>
        <textarea id="instructions" name="instructions" class="form-control" rows="3">{{ $value('instructions') }}</textarea>
    </div>
    <div style="margin-top:16px">
        <label for="notes">Notes</label>
        <textarea id="notes" name="notes" class="form-control" rows="2">{{ $value('notes') }}</textarea>
    </div>

    <hr>
    <h2>Recipe components</h2>
    <p>Enter the metal components (metal charge, returns, alloy) all as fixed kg or all as percentages of the target total charge. Coke, fuel, flux and other additions go on top: kg per charge, or in a percentage recipe also a percent of the metal charge. They are not counted in the 100%.</p>

    <div style="overflow-x:auto">
        <table class="table" id="components-table">
            <thead>
                <tr><th>Material</th><th>Role</th><th>Charged</th><th>Fixed kg</th><th>Percent %</th><th>Calculated kg</th><th>% of total</th><th>Min kg</th><th>Max kg</th><th>Required</th><th>Notes</th><th></th></tr>
            </thead>
            <tbody id="components-body">
            @php($oldItems = old('items', $editing && $chargeBatch->items->isNotEmpty()
                ? $chargeBatch->items->map(fn ($i) => ['material_id' => $i->material_id, 'component_role' => $i->component_role, 'charge_stage' => $i->charge_stage, 'target_qty_kg' => $i->target_qty_kg, 'target_percent' => $i->target_percent, 'minimum_qty_kg' => $i->minimum_qty_kg, 'maximum_qty_kg' => $i->maximum_qty_kg, 'is_required' => $i->is_required ? '1' : '0', 'notes' => $i->notes])->all()
                : [['material_id'=>'','component_role'=>'CHARGE','target_qty_kg'=>'','target_percent'=>'','minimum_qty_kg'=>'','maximum_qty_kg'=>'','is_required'=>'1','notes'=>'']]))
            @foreach($oldItems as $i => $item)
                <tr>
                    <td>
                        <select name="items[{{ $i }}][material_id]" class="form-control material-select" required>
                            <option value="">Select material</option>
                            @foreach($materials as $material)
                                <option value="{{ $material->id }}" data-type="{{ $material->material_type }}" @selected(($item['material_id'] ?? '') == $material->id)>{{ $material->code }} — {{ $material->name }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td>@php($role = strtoupper((string) ($item['component_role'] ?? 'CHARGE')))<select name="items[{{ $i }}][component_role]" class="form-control role-select" required>@foreach ($rowRoles($role) as $code => $label)<option value="{{ $code }}" @selected($role === $code)>{{ $label }}</option>@endforeach</select></td>
                    <td><select name="items[{{ $i }}][charge_stage]" class="form-control"><option value="ROUND">Every charge</option><option value="BED" @selected(($item['charge_stage'] ?? 'ROUND') === 'BED')>Start-up only</option></select></td>
                    <td><input name="items[{{ $i }}][target_qty_kg]" type="number" min="0" step="any" class="form-control fixed-qty" value="{{ $item['target_qty_kg'] ?? '' }}"></td>
                    <td><input name="items[{{ $i }}][target_percent]" type="number" min="0" max="100" step="any" class="form-control percentage" value="{{ $item['target_percent'] ?? '' }}"></td>
                    <td class="calculated-kg muted">—</td>
                    <td class="calculated-percent muted">—</td>
                    <td><input name="items[{{ $i }}][minimum_qty_kg]" type="number" min="0" step="any" class="form-control" value="{{ $item['minimum_qty_kg'] ?? '' }}"></td>
                    <td><input name="items[{{ $i }}][maximum_qty_kg]" type="number" min="0" step="any" class="form-control" value="{{ $item['maximum_qty_kg'] ?? '' }}"></td>
                    <td style="text-align:center"><input type="checkbox" name="items[{{ $i }}][is_required]" value="1" @checked(($item['is_required'] ?? '0') == '1')></td>
                    <td><input name="items[{{ $i }}][notes]" class="form-control" value="{{ $item['notes'] ?? '' }}"></td>
                    <td>@include('partials.icon-button', ['type' => 'delete', 'word' => 'Remove', 'buttonType' => 'button', 'class' => 'remove-component'])</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <button type="button" class="btn btn-secondary" id="add-component">+ Add component</button>
    <span class="muted small" style="margin-left:8px">For coke, fuel, flux or another addition, add a component and pick its role.</span>
    <div id="recipe-summary" class="alert alert-info" role="status" aria-live="polite" style="margin-top:12px">Add components to calculate the total charge weight and each material's percentage.</div>

    <div style="margin-top:20px">
        <button class="btn btn-primary" type="submit" @disabled($materials->isEmpty())>Save recipe</button>
        <a class="btn btn-secondary" href="{{ $editing ? route('charge-batches.show', $chargeBatch) : route('charge-batches.index') }}">Cancel</a>
    </div>
</form>

<script>
(() => {
    const body = document.getElementById('components-body');
    const addButton = document.getElementById('add-component');
    const summary = document.getElementById('recipe-summary');
    const totalInput = document.getElementById('target_total_charge_weight_kg');
    const totalHelp = document.getElementById('charge-weight-help');
    const materials = @json($materials->map(fn($m) => ['id' => $m->id, 'label' => $m->code.' — '.$m->name, 'type' => $m->material_type])->values());
    const roles = @json($roles);
    const additions = @json($additions);
    const roleForType = @json(\App\Models\ChargeBatch::ROLE_FOR_TYPE);
    const isAddition = row => additions.includes(row.querySelector('.role-select').value);

    const number = value => value.trim() === '' ? null : Number(value);
    const fmt = (value, decimals = 3) => value === null || !Number.isFinite(value)
        ? '—'
        : value.toLocaleString(undefined, {minimumFractionDigits: decimals, maximumFractionDigits: decimals});

    function renumber() {
        [...body.rows].forEach((row, index) => {
            row.querySelectorAll('[name]').forEach(input => {
                input.name = input.name.replace(/items\[\d+\]/, `items[${index}]`);
            });
        });
        updateSummary();
    }

    function updateSummary() {
        const rows = [...body.rows];
        const rowValues = rows.map(row => ({
            row,
            addition: isAddition(row),
            qty: number(row.querySelector('.fixed-qty').value),
            percent: number(row.querySelector('.percentage').value),
        }));
        const metal = rowValues.filter(item => !item.addition);
        const extras = rowValues.filter(item => item.addition);
        const fixedRows = metal.filter(item => item.qty !== null && Number.isFinite(item.qty));
        const percentRows = metal.filter(item => item.percent !== null && Number.isFinite(item.percent));
        const hasFixed = fixedRows.length > 0;
        const hasPercent = percentRows.length > 0;
        const fixedTotal = fixedRows.reduce((sum, item) => sum + item.qty, 0);
        const percentTotal = percentRows.reduce((sum, item) => sum + item.percent, 0);
        const basis = number(totalInput.value);
        const allFixedFilled = metal.length > 0 && fixedRows.length === metal.length;
        const allPercentFilled = metal.length > 0 && percentRows.length === metal.length;
        const extrasKg = basisKg => extras.reduce((sum, {qty, percent}) =>
            sum + (qty ?? 0) + (percent !== null && basisKg ? basisKg * percent / 100 : 0), 0);

        rows.forEach(row => {
            row.querySelector('.calculated-kg').textContent = '—';
            row.querySelector('.calculated-percent').textContent = '—';
        });
        const showExtras = basisKg => extras.forEach(({row, qty, percent}) => {
            const kg = qty ?? (percent !== null && basisKg ? basisKg * percent / 100 : null);
            row.querySelector('.calculated-kg').textContent = kg !== null ? fmt(kg) : (percent !== null ? 'Set total kg' : '—');
            row.querySelector('.calculated-percent').textContent = kg !== null && basisKg ? fmt(kg / basisKg * 100, 2) + '% of metal' : '—';
        });
        const extrasNote = basisKg => extras.length ? ` | Coke, fuel and additions: ${fmt(extrasKg(basisKg))} kg on top` : '';

        if (metal.length === 0) {
            summary.className = 'alert alert-warning';
            summary.textContent = 'Add at least one metal component (metal charge, returns or alloy). Coke, fuel and flux go on top of it.';
            showExtras(null);
            return;
        }

        if (hasFixed && hasPercent) {
            totalInput.readOnly = false;
            totalInput.required = false;
            totalHelp.textContent = 'Choose one method for the metal components: fixed kg or percentage.';
            summary.className = 'alert alert-danger';
            summary.textContent = 'Invalid: do not mix fixed kg and percentages for the metal components. Clear one input method. Total charge cannot be calculated until this is corrected.';
            return;
        }

        if (hasPercent) {
            totalInput.readOnly = false;
            totalInput.required = true;
            totalHelp.textContent = 'Required for percentage recipes: the metal charge. Each metal kg = total charge kg × percentage ÷ 100.';
            const hasBasis = basis !== null && Number.isFinite(basis) && basis > 0;
            metal.forEach(({row, percent}) => {
                if (percent === null || !Number.isFinite(percent)) return;
                row.querySelector('.calculated-kg').textContent = hasBasis ? fmt(basis * percent / 100) : 'Set total kg';
                row.querySelector('.calculated-percent').textContent = fmt(percent, 4) + '%';
            });
            showExtras(hasBasis ? basis : null);

            const validTotal = Math.abs(percentTotal - 100) <= 0.01;
            const weightLabel = hasBasis ? `${fmt(basis)} kg` : 'not set';
            const messages = [`Metal charge: ${weightLabel}`, `Metal percentages: ${fmt(percentTotal, 4)}%`];
            if (!allPercentFilled) messages.push('Enter a percentage for every metal component');
            if (!validTotal || !allPercentFilled) messages.push('metal components must total 100%');
            if (!hasBasis) messages.push('enter target total charge kg');
            summary.className = (validTotal && allPercentFilled && hasBasis) ? 'alert alert-success' : 'alert alert-warning';
            summary.textContent = messages.join(' | ') + extrasNote(hasBasis ? basis : null);
            return;
        }

        if (hasFixed) {
            const percentExtras = extras.some(item => item.percent !== null);
            const total = fixedTotal + extrasKg(null);
            totalInput.readOnly = true;
            totalInput.required = false;
            totalInput.value = total > 0 ? total.toFixed(3) : '';
            totalHelp.textContent = 'Calculated automatically from the fixed quantities entered below.';
            metal.forEach(({row, qty}) => {
                if (qty === null || !Number.isFinite(qty)) return;
                row.querySelector('.calculated-kg').textContent = fmt(qty);
                row.querySelector('.calculated-percent').textContent = fixedTotal > 0 ? fmt(qty / fixedTotal * 100, 4) + '%' : '—';
            });
            showExtras(fixedTotal);
            const ok = allFixedFilled && fixedTotal > 0 && !percentExtras;
            summary.className = ok ? 'alert alert-success' : 'alert alert-warning';
            summary.textContent = `Metal charge: ${fmt(fixedTotal)} kg | Method: fixed quantities${extrasNote(fixedTotal)} | `
                + (percentExtras ? 'In a fixed-kg recipe enter coke, fuel and flux in kg per charge too.' : (allFixedFilled ? 'Each material shows its share of the metal.' : 'Enter a fixed kg quantity for every metal component.'));
            return;
        }

        totalInput.readOnly = false;
        totalInput.required = false;
        totalHelp.textContent = 'Required for percentage recipes. Fixed-kg recipes calculate this total automatically.';
        summary.className = 'alert alert-info';
        summary.textContent = 'Add components and enter fixed kg values or percentages. Fixed kg values are summed into a total; percentages require a target total charge weight.';
    }

    function addRow() {
        const index = body.rows.length;
        const row = document.createElement('tr');
        const options = materials.map(material =>
            `<option value="${material.id}" data-type="${escapeHtml(material.type ?? '')}">${escapeHtml(material.label)}</option>`
        ).join('');
        const roleOptions = Object.entries(roles).map(([code, label]) => `<option value="${code}">${escapeHtml(label)}</option>`).join('');

        row.innerHTML = `
            <td><select name="items[${index}][material_id]" class="form-control material-select" required><option value="">Select material</option>${options}</select></td>
            <td><select name="items[${index}][component_role]" class="form-control role-select" required>${roleOptions}</select></td>
            <td><select name="items[${index}][charge_stage]" class="form-control"><option value="ROUND">Every charge</option><option value="BED">Start-up only</option></select></td>
            <td><input name="items[${index}][target_qty_kg]" type="number" min="0" step="any" class="form-control fixed-qty"></td>
            <td><input name="items[${index}][target_percent]" type="number" min="0" max="100" step="any" class="form-control percentage"></td>
            <td class="calculated-kg muted">—</td>
            <td class="calculated-percent muted">—</td>
            <td><input name="items[${index}][minimum_qty_kg]" type="number" min="0" step="any" class="form-control"></td>
            <td><input name="items[${index}][maximum_qty_kg]" type="number" min="0" step="any" class="form-control"></td>
            <td style="text-align:center"><input type="checkbox" name="items[${index}][is_required]" value="1" checked></td>
            <td><input name="items[${index}][notes]" class="form-control" maxlength="2000"></td>
            <td>@include('partials.icon-button', ['type' => 'delete', 'word' => 'Remove', 'buttonType' => 'button', 'class' => 'remove-component'])</td>`;
        body.appendChild(row);
        updateSummary();
    }

    function escapeHtml(value) {
        return value.replace(/[&<>"']/g, char => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
        }[char]));
    }

    addButton.addEventListener('click', addRow);
    body.addEventListener('click', event => {
        if (event.target.closest('.remove-component')) {
            if (body.rows.length > 1) {
                event.target.closest('tr').remove();
                renumber();
            } else {
                alert('A recipe must have at least one component.');
            }
        }
    });
    body.addEventListener('input', updateSummary);
    body.addEventListener('change', event => {
        // Picking fuel, flux, returns or an alloy sets the row's role while it is still the default metal charge.
        const select = event.target.closest('.material-select');
        if (select) {
            const role = roleForType[select.selectedOptions[0]?.dataset.type ?? ''];
            const roleSelect = select.closest('tr').querySelector('.role-select');
            if (role && roleSelect.value === 'CHARGE') roleSelect.value = role;
        }
        updateSummary();
    });
    totalInput.addEventListener('input', updateSummary);
    totalInput.addEventListener('change', updateSummary);
    updateSummary();
})();
</script>
