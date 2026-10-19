@csrf

<div class="form-grid" data-progressive="3">
    <div>
        <label for="code">Material code *</label>
        <input id="code" name="code" class="form-control" required maxlength="50"
               value="{{ old('code', $material->code) }}">
        @error('code') <small class="text-danger">{{ $message }}</small> @enderror
    </div>

    <div>
        <label for="name">Material name *</label>
        <input id="name" name="name" class="form-control" required maxlength="150"
               value="{{ old('name', $material->name) }}">
        @error('name') <small class="text-danger">{{ $message }}</small> @enderror
    </div>

    <div>
        <label for="material_type">Material type *</label>
        <select id="material_type" name="material_type" class="form-control" required>
            @foreach(['METAL','RETURN','ALLOY','FLUX','FUEL','SAND','BINDER','ADDITIVE','OTHER'] as $type)
                <option value="{{ $type }}" @selected(old('material_type', $material->material_type ?: 'METAL') === $type)>
                    {{ $type }}
                </option>
            @endforeach
        </select>
        @error('material_type') <small class="text-danger">{{ $message }}</small> @enderror
    </div>

    <div>
        <label for="base_uom">Base unit *</label>
        <select id="base_uom" name="base_uom" class="form-control" required>
            @foreach(['kg','tonne','litre','piece','bag','box'] as $unit)
                <option value="{{ $unit }}" @selected(old('base_uom', $material->base_uom ?: 'kg') === $unit)>
                    {{ $unit }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <label for="status">Status *</label>
        <select id="status" name="status" class="form-control" required>
            @foreach(['ACTIVE','INACTIVE'] as $status)
                <option value="{{ $status }}" @selected(old('status', $material->status ?: 'ACTIVE') === $status)>
                    {{ $status }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <label for="standard_cost_per_uom">Standard cost per unit (PKR)</label>
        <input id="standard_cost_per_uom" name="standard_cost_per_uom" type="number"
               min="0" step="0.0001" class="form-control"
               value="{{ old('standard_cost_per_uom', $material->standard_cost_per_uom) }}">
    </div>

    <div>
        <label for="minimum_stock_qty">Minimum stock quantity</label>
        <input id="minimum_stock_qty" name="minimum_stock_qty" type="number"
               min="0" step="0.001" class="form-control"
               value="{{ old('minimum_stock_qty', $material->minimum_stock_qty) }}">
    </div>

    <div>
        <label for="reorder_qty">Reorder quantity</label>
        <input id="reorder_qty" name="reorder_qty" type="number"
               min="0" step="0.001" class="form-control"
               value="{{ old('reorder_qty', $material->reorder_qty) }}">
    </div>
</div>

<div style="display:flex;gap:24px;flex-wrap:wrap;margin:18px 0">
    <label>
        <input type="hidden" name="is_stock_tracked" value="0">
        <input type="checkbox" name="is_stock_tracked" value="1"
               @checked(old('is_stock_tracked', $material->is_stock_tracked ?? true))>
        Track inventory stock
    </label>

    <label>
        <input type="hidden" name="requires_lot_tracking" value="0">
        <input type="checkbox" name="requires_lot_tracking" value="1"
               @checked(old('requires_lot_tracking', $material->requires_lot_tracking ?? true))>
        Require lot traceability
    </label>
</div>

<div style="margin-bottom:16px">
    <label for="description">Description</label>
    <textarea id="description" name="description" class="form-control" rows="3">{{ old('description', $material->description) }}</textarea>
</div>

<div style="margin-bottom:16px">
    <label for="notes">Internal notes</label>
    <textarea id="notes" name="notes" class="form-control" rows="3">{{ old('notes', $material->notes) }}</textarea>
</div>


<div style="display:flex;gap:10px">
    <button class="btn btn-primary" type="submit">Save material</button>
    <a class="btn btn-secondary" href="{{ route('materials.index') }}">Cancel</a>
</div>
