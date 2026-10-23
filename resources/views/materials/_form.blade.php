@csrf

<div class="panel">
    <div class="panel-header"><h2>Identity</h2></div>
    <div class="panel-body"><div class="form-grid">
        <div class="field">
            <label for="code">Material code <span class="required">*</span></label>
            <input id="code" name="code" required maxlength="50" value="{{ old('code', $material->code) }}">
            @error('code')<div class="error">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label for="name">Material name <span class="required">*</span></label>
            <input id="name" name="name" required maxlength="150" value="{{ old('name', $material->name) }}">
            @error('name')<div class="error">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label for="material_type">Material type <span class="required">*</span></label>
            <select id="material_type" name="material_type" required>
                @foreach (\App\Models\Material::TYPES as $key => $label)
                    <option value="{{ $key }}" @selected(old('material_type', $material->material_type ?: 'METAL') === $key)>{{ $label }}</option>
                @endforeach
            </select>
            @error('material_type')<div class="error">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label for="base_uom">Base unit <span class="required">*</span></label>
            <select id="base_uom" name="base_uom" required>
                @foreach (\App\Models\Material::UNITS as $unit)
                    <option value="{{ $unit }}" @selected(old('base_uom', $material->base_uom ?: 'kg') === $unit)>{{ $unit }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="status">Status <span class="required">*</span></label>
            <select id="status" name="status" required>
                @foreach (['ACTIVE' => 'Active', 'INACTIVE' => 'Inactive'] as $key => $label)
                    <option value="{{ $key }}" @selected(old('status', $material->status ?: 'ACTIVE') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="standard_cost_per_uom">Standard cost per unit (PKR)</label>
            <input id="standard_cost_per_uom" name="standard_cost_per_uom" type="number" min="0" step="0.0001" value="{{ old('standard_cost_per_uom', $material->standard_cost_per_uom) }}">
            @error('standard_cost_per_uom')<div class="error">{{ $message }}</div>@enderror
        </div>
    </div></div>
</div>

<div class="panel">
    <div class="panel-header"><h2>Stock</h2></div>
    <div class="panel-body"><div class="form-grid">
        <div class="field">
            <label for="minimum_stock_qty">Minimum stock quantity</label>
            <input id="minimum_stock_qty" name="minimum_stock_qty" type="number" min="0" step="0.001" value="{{ old('minimum_stock_qty', $material->minimum_stock_qty) }}">
            <div class="help">Everyone who manages stores is told when stock falls to this.</div>
            @error('minimum_stock_qty')<div class="error">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label for="reorder_qty">Reorder quantity</label>
            <input id="reorder_qty" name="reorder_qty" type="number" min="0" step="0.001" value="{{ old('reorder_qty', $material->reorder_qty) }}">
            <div class="help">The suggested order size. Blank orders back up to the minimum.</div>
            @error('reorder_qty')<div class="error">{{ $message }}</div>@enderror
        </div>
        <div class="field field-full">
            <label class="check-field">
                <input type="hidden" name="is_stock_tracked" value="0">
                <input type="checkbox" name="is_stock_tracked" value="1" @checked(old('is_stock_tracked', $material->is_stock_tracked ?? true))>
                Track inventory stock
            </label>
            <label class="check-field">
                <input type="hidden" name="requires_lot_tracking" value="0">
                <input type="checkbox" name="requires_lot_tracking" value="1" @checked(old('requires_lot_tracking', $material->requires_lot_tracking ?? true))>
                Require lot traceability
            </label>
        </div>
    </div></div>
</div>

<div class="panel">
    <div class="panel-header"><h2>Notes</h2></div>
    <div class="panel-body"><div class="form-grid">
        <div class="field field-full">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="3">{{ old('description', $material->description) }}</textarea>
        </div>
        <div class="field field-full">
            <label for="notes">Internal notes</label>
            <textarea id="notes" name="notes" rows="3">{{ old('notes', $material->notes) }}</textarea>
        </div>
    </div></div>
</div>

<div class="actions">
    <button class="btn btn-primary" type="submit">Save material</button>
    <a class="btn" href="{{ $material->exists ? route('materials.show', $material) : route('materials.index') }}">Cancel</a>
</div>
