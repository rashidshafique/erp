@extends('layouts.app')

@section('content')
<div class="page-header">
    <div><h1>New charge recipe</h1><p>Choose one calculation method: fixed quantities or percentages.</p></div>
</div>

@if($errors->any())
    <div class="alert alert-danger">
        <strong>Please correct the following:</strong>
        <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

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
    <p>For percentage recipes, percentages must total 100%. Percentage quantities are calculated from the target total charge weight, not target tapped metal weight.</p>

    <div style="overflow-x:auto">
        <table class="table" id="components-table">
            <thead>
                <tr><th>Material</th><th>Role</th><th>Fixed kg</th><th>Percent %</th><th>Min kg</th><th>Max kg</th><th>Required</th><th>Notes</th><th></th></tr>
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
                    <td><input name="items[{{ $i }}][target_qty_kg]" type="number" min="0.001" step="0.001" class="form-control fixed-qty" value="{{ $item['target_qty_kg'] ?? '' }}"></td>
                    <td><input name="items[{{ $i }}][target_percent]" type="number" min="0.001" max="100" step="0.0001" class="form-control percentage" value="{{ $item['target_percent'] ?? '' }}"></td>
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
    <p id="recipe-summary" style="margin-top:12px"></p>

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
    const materials = @json($materials->map(fn($m) => ['id' => $m->id, 'label' => $m->code.' — '.$m->name])->values());

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
        const percentages = rows.map(row => row.querySelector('.percentage').value.trim()).filter(Boolean);
        const fixed = rows.map(row => row.querySelector('.fixed-qty').value.trim()).filter(Boolean);
        const total = percentages.reduce((sum, value) => sum + Number(value), 0);

        if (percentages.length && fixed.length) {
            summary.textContent = 'Error: use fixed quantities or percentages, not both.';
        } else if (percentages.length) {
            summary.textContent = `Percentage total: ${total.toFixed(4)}%${Math.abs(total - 100) > 0.01 ? ' — must total 100%.' : ' — valid total.'}`;
        } else if (fixed.length) {
            summary.textContent = 'Fixed-quantity recipe. Enter a fixed kg quantity for every component.';
        } else {
            summary.textContent = 'Add recipe components.';
        }
    }

    function addRow() {
        const index = body.rows.length;
        const row = document.createElement('tr');
        const options = materials.map(material =>
            `<option value="${material.id}">${escapeHtml(material.label)}</option>`
        ).join('');

        row.innerHTML = `
            <td><select name="items[${index}][material_id]" class="form-control" required><option value="">Select material</option>${options}</select></td>
            <td><input name="items[${index}][component_role]" class="form-control" required value="CHARGE"></td>
            <td><input name="items[${index}][target_qty_kg]" type="number" min="0.001" step="0.001" class="form-control fixed-qty"></td>
            <td><input name="items[${index}][target_percent]" type="number" min="0.001" max="100" step="0.0001" class="form-control percentage"></td>
            <td><input name="items[${index}][minimum_qty_kg]" type="number" min="0" step="0.001" class="form-control"></td>
            <td><input name="items[${index}][maximum_qty_kg]" type="number" min="0.001" step="0.001" class="form-control"></td>
            <td style="text-align:center"><input type="checkbox" name="items[${index}][is_required]" value="1" checked></td>
            <td><input name="items[${index}][notes]" class="form-control"></td>
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
    updateSummary();
})();
</script>
@endsection
