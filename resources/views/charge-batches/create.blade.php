@extends('layouts.app')

@section('content')
<div class="page-header">
    <div><h1>New charge recipe</h1><p>Choose one calculation method: fixed quantities or percentages.</p></div>
</div>


@if($materials->isEmpty())
    <div class="alert alert-warning">
        Create active materials before defining a recipe.
        <a href="{{ route('materials.create') }}">Create a material</a>
    </div>
@endif

<form method="POST" action="{{ route('charge-batches.store') }}" id="recipe-form">
    @csrf

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px">
        <div>
            <label for="code">Recipe code *</label>
            <input id="code" name="code" class="form-control" required value="{{ old('code') }}">
        </div>
        <div>
            <label for="name">Recipe name *</label>
            <input id="name" name="name" class="form-control" required value="{{ old('name') }}">
        </div>
        <div>
            <label for="process_type">Process *</label>
            <select id="process_type" name="process_type" class="form-control" required>
                @foreach(['CUPOLA','INDUCTION','OTHER'] as $process)
                    <option value="{{ $process }}" @selected(old('process_type', 'CUPOLA') === $process)>{{ $process }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="status">Status *</label>
            <select id="status" name="status" class="form-control" required>
                @foreach(['DRAFT','ACTIVE','INACTIVE'] as $status)
                    <option value="{{ $status }}" @selected(old('status', 'DRAFT') === $status)>{{ $status }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="revision">Revision *</label>
            <input id="revision" name="revision" class="form-control" required value="{{ old('revision', 'A') }}">
        </div>
        <div>
            <label for="target_metal_weight_kg">Target tapped metal (kg)</label>
            <input id="target_metal_weight_kg" name="target_metal_weight_kg" type="number" min="0.001" step="0.001" class="form-control" value="{{ old('target_metal_weight_kg') }}">
        </div>
        <div>
            <label for="target_total_charge_weight_kg">Target total charge (kg)</label>
            <input id="target_total_charge_weight_kg" name="target_total_charge_weight_kg" type="number" min="0.001" step="0.001" class="form-control" value="{{ old('target_total_charge_weight_kg') }}">
            <small class="muted" id="charge-weight-help">Required for percentage recipes. Fixed-kg recipes calculate this total automatically.</small>
        </div>
        <div>
            <label for="effective_from">Effective from</label>
            <input id="effective_from" name="effective_from" type="date" class="form-control" value="{{ old('effective_from') }}">
        </div>
        <div>
            <label for="effective_to">Effective to</label>
            <input id="effective_to" name="effective_to" type="date" class="form-control" value="{{ old('effective_to') }}">
        </div>
    </div>

    <div style="margin-top:16px">
        <label for="instructions">Operating instructions</label>
        <textarea id="instructions" name="instructions" class="form-control" rows="3">{{ old('instructions') }}</textarea>
    </div>
    <div style="margin-top:16px">
        <label for="notes">Notes</label>
        <textarea id="notes" name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
    </div>

    <hr>
    <h2>Recipe components</h2>
    <p>Enter each component as either a fixed kg quantity or a percentage. A recipe uses one method throughout. Fixed quantities are summed automatically; percentage recipes use the target total charge weight as their kg basis.</p>

    <div style="overflow-x:auto">
        <table class="table" id="components-table">
            <thead>
                <tr><th>Material</th><th>Role</th><th>Charged</th><th>Fixed kg</th><th>Percent %</th><th>Calculated kg</th><th>% of total</th><th>Min kg</th><th>Max kg</th><th>Required</th><th>Notes</th><th></th></tr>
            </thead>
            <tbody id="components-body">
            @php($oldItems = old('items', [['material_id'=>'','component_role'=>'CHARGE','target_qty_kg'=>'','target_percent'=>'','minimum_qty_kg'=>'','maximum_qty_kg'=>'','is_required'=>'1','notes'=>'']]))
            @foreach($oldItems as $i => $item)
                <tr>
                    <td>
                        <select name="items[{{ $i }}][material_id]" class="form-control" required>
                            <option value="">Select material</option>
                            @foreach($materials as $material)
                                <option value="{{ $material->id }}" @selected(($item['material_id'] ?? '') == $material->id)>{{ $material->code }} — {{ $material->name }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td><input name="items[{{ $i }}][component_role]" class="form-control" required value="{{ $item['component_role'] ?? 'CHARGE' }}"></td>
                    <td><select name="items[{{ $i }}][charge_stage]" class="form-control"><option value="ROUND">Every charge</option><option value="BED" @selected(($item['charge_stage'] ?? 'ROUND') === 'BED')>Start-up only</option></select></td>
                    <td><input name="items[{{ $i }}][target_qty_kg]" type="number" min="0.001" step="0.001" class="form-control fixed-qty" value="{{ $item['target_qty_kg'] ?? '' }}"></td>
                    <td><input name="items[{{ $i }}][target_percent]" type="number" min="0.001" max="100" step="0.0001" class="form-control percentage" value="{{ $item['target_percent'] ?? '' }}"></td>
                    <td class="calculated-kg muted">—</td>
                    <td class="calculated-percent muted">—</td>
                    <td><input name="items[{{ $i }}][minimum_qty_kg]" type="number" min="0" step="0.001" class="form-control" value="{{ $item['minimum_qty_kg'] ?? '' }}"></td>
                    <td><input name="items[{{ $i }}][maximum_qty_kg]" type="number" min="0.001" step="0.001" class="form-control" value="{{ $item['maximum_qty_kg'] ?? '' }}"></td>
                    <td style="text-align:center"><input type="checkbox" name="items[{{ $i }}][is_required]" value="1" @checked(($item['is_required'] ?? '0') == '1')></td>
                    <td><input name="items[{{ $i }}][notes]" class="form-control" value="{{ $item['notes'] ?? '' }}"></td>
                    <td>@include('partials.icon-button', ['type' => 'delete', 'word' => 'Remove', 'buttonType' => 'button', 'class' => 'remove-component'])</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <button type="button" class="btn btn-secondary" id="add-component">+ Add component</button>
    <div id="recipe-summary" class="alert alert-info" role="status" aria-live="polite" style="margin-top:12px">Add components to calculate the total charge weight and each material's percentage.</div>

    <div style="margin-top:20px">
        <button class="btn btn-primary" type="submit" @disabled($materials->isEmpty())>Save recipe</button>
        <a class="btn btn-secondary" href="{{ route('charge-batches.index') }}">Cancel</a>
    </div>
</form>

<script>
(() => {
    const body = document.getElementById('components-body');
    const addButton = document.getElementById('add-component');
    const summary = document.getElementById('recipe-summary');
    const totalInput = document.getElementById('target_total_charge_weight_kg');
    const totalHelp = document.getElementById('charge-weight-help');
    const materials = @json($materials->map(fn($m) => ['id' => $m->id, 'label' => $m->code.' — '.$m->name])->values());

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
            qty: number(row.querySelector('.fixed-qty').value),
            percent: number(row.querySelector('.percentage').value),
        }));
        const fixedRows = rowValues.filter(item => item.qty !== null && Number.isFinite(item.qty));
        const percentRows = rowValues.filter(item => item.percent !== null && Number.isFinite(item.percent));
        const hasFixed = fixedRows.length > 0;
        const hasPercent = percentRows.length > 0;
        const fixedTotal = fixedRows.reduce((sum, item) => sum + item.qty, 0);
        const percentTotal = percentRows.reduce((sum, item) => sum + item.percent, 0);
        const basis = number(totalInput.value);
        const allFixedFilled = rows.length > 0 && fixedRows.length === rows.length;
        const allPercentFilled = rows.length > 0 && percentRows.length === rows.length;

        rows.forEach(row => {
            row.querySelector('.calculated-kg').textContent = '—';
            row.querySelector('.calculated-percent').textContent = '—';
        });

        if (hasFixed && hasPercent) {
            totalInput.readOnly = false;
            totalInput.required = false;
            totalHelp.textContent = 'Choose one method for the entire recipe: fixed kg or percentage.';
            summary.className = 'alert alert-danger';
            summary.textContent = 'Invalid: do not mix fixed kg and percentages in one recipe. Clear one input method. Total charge cannot be calculated until this is corrected.';
            return;
        }

        if (hasPercent) {
            totalInput.readOnly = false;
            totalInput.required = true;
            totalHelp.textContent = 'Required for percentage recipes. Each material kg = total charge kg × percentage ÷ 100.';
            const hasBasis = basis !== null && Number.isFinite(basis) && basis > 0;
            rowValues.forEach(({row, percent}) => {
                if (percent === null || !Number.isFinite(percent)) return;
                row.querySelector('.calculated-kg').textContent = hasBasis ? fmt(basis * percent / 100) : 'Set total kg';
                row.querySelector('.calculated-percent').textContent = fmt(percent, 4) + '%';
            });

            const validTotal = Math.abs(percentTotal - 100) <= 0.01;
            const weightLabel = hasBasis ? `${fmt(basis)} kg` : 'not set';
            const messages = [`Total charge: ${weightLabel}`, `Entered percentages: ${fmt(percentTotal, 4)}%`];
            if (!allPercentFilled) messages.push('Enter a percentage for every material');
            if (!validTotal || !allPercentFilled) messages.push('percentage recipe must total 100%');
            if (!hasBasis) messages.push('enter target total charge kg');
            summary.className = (validTotal && allPercentFilled && hasBasis) ? 'alert alert-success' : 'alert alert-warning';
            summary.textContent = messages.join(' | ');
            return;
        }

        if (hasFixed) {
            totalInput.readOnly = true;
            totalInput.required = false;
            totalInput.value = fixedTotal > 0 ? fixedTotal.toFixed(3) : '';
            totalHelp.textContent = 'Calculated automatically from the fixed quantities entered below.';
            rowValues.forEach(({row, qty}) => {
                if (qty === null || !Number.isFinite(qty)) return;
                row.querySelector('.calculated-kg').textContent = fmt(qty);
                row.querySelector('.calculated-percent').textContent = fixedTotal > 0 ? fmt(qty / fixedTotal * 100, 4) + '%' : '—';
            });
            summary.className = (allFixedFilled && fixedTotal > 0) ? 'alert alert-success' : 'alert alert-warning';
            summary.textContent = `Total charge: ${fmt(fixedTotal)} kg | Method: fixed quantities | ${allFixedFilled ? 'Each material shows its share of the total.' : 'Enter a fixed kg quantity for every material.'}`;
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
            `<option value="${material.id}">${escapeHtml(material.label)}</option>`
        ).join('');

        row.innerHTML = `
            <td><select name="items[${index}][material_id]" class="form-control" required><option value="">Select material</option>${options}</select></td>
            <td><input name="items[${index}][component_role]" class="form-control" required value="CHARGE" maxlength="40"></td>
            <td><select name="items[${index}][charge_stage]" class="form-control"><option value="ROUND">Every charge</option><option value="BED">Start-up only</option></select></td>
            <td><input name="items[${index}][target_qty_kg]" type="number" min="0.001" step="0.001" class="form-control fixed-qty"></td>
            <td><input name="items[${index}][target_percent]" type="number" min="0.001" max="100" step="0.0001" class="form-control percentage"></td>
            <td class="calculated-kg muted">—</td>
            <td class="calculated-percent muted">—</td>
            <td><input name="items[${index}][minimum_qty_kg]" type="number" min="0" step="0.001" class="form-control"></td>
            <td><input name="items[${index}][maximum_qty_kg]" type="number" min="0.001" step="0.001" class="form-control"></td>
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
    body.addEventListener('change', updateSummary);
    totalInput.addEventListener('input', updateSummary);
    totalInput.addEventListener('change', updateSummary);
    updateSummary();
})();
</script>
@endsection
