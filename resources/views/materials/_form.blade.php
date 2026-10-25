@csrf

<div class="panel">
    <div class="panel-header"><h2>Identity</h2></div>
    <div class="panel-body"><div class="form-grid">
        <div class="field">
            <label for="code">Material code</label>
            <input id="code" name="code" maxlength="50" value="{{ old('code', $material->code) }}" style="text-transform:uppercase" placeholder="{{ $material->exists ? '' : 'Leave blank to generate: '.app(\App\Services\Codes\CodeGenerator::class)->preview('material') }}">
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
            <input id="standard_cost_per_uom" name="standard_cost_per_uom" type="number" min="0" step="any" value="{{ old('standard_cost_per_uom', $material->standard_cost_per_uom) }}">
            @error('standard_cost_per_uom')<div class="error">{{ $message }}</div>@enderror
        </div>
    </div></div>
</div>

<div class="panel">
    <div class="panel-header"><h2>Tax and reporting</h2></div>
    <div class="panel-body">@include('partials.trade-fields', ['item' => $material])</div>
</div>

<div class="panel">
    <div class="panel-header"><h2>Stock</h2></div>
    <div class="panel-body"><div class="form-grid">
        <div class="field">
            <label for="minimum_stock_qty">Minimum stock quantity</label>
            <input id="minimum_stock_qty" name="minimum_stock_qty" type="number" min="0" step="any" value="{{ old('minimum_stock_qty', $material->minimum_stock_qty) }}">
            <div class="help">Everyone who manages stores is told when stock falls to this.</div>
            @error('minimum_stock_qty')<div class="error">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label for="reorder_qty">Reorder quantity</label>
            <input id="reorder_qty" name="reorder_qty" type="number" min="0" step="any" value="{{ old('reorder_qty', $material->reorder_qty) }}">
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
    <div class="panel-header"><h2>Chemistry <span class="muted small">(optional)</span></h2></div>
    <div class="panel-body">
        <p class="muted">The typical analysis of this material, in percent. When it is on file, a heat's charge is worked out from it and compared with the iron grade before the metal is tested. Leave it blank for materials you do not track, and for any element you do not know.</p>
        @php $chem = $material->exists ? $material->elements->keyBy('element') : collect(); @endphp
        <div class="table-wrap"><table>
            <thead><tr><th>Element</th><th>Typical %</th><th>Lowest %</th><th>Highest %</th><th>Recovery %</th></tr></thead>
            <tbody>
            @foreach (\App\Models\IronGrade::ELEMENTS as $key => $label)
                @php $row = $chem->get($key); @endphp
                <tr>
                    <td><strong>{{ $label }}</strong></td>
                    @foreach (['nominal_pct' => 'Typical', 'min_pct' => 'Lowest', 'max_pct' => 'Highest', 'recovery_pct' => 'Recovery'] as $field => $name)
                        <td><input type="number" step="any" min="0" max="{{ $field === 'recovery_pct' ? 300 : 100 }}" style="width:110px" name="chemistry[{{ $key }}][{{ $field }}]" aria-label="{{ $label }} {{ strtolower($name) }}"
                                   value="{{ old("chemistry.{$key}.{$field}", $row?->{$field} !== null ? rtrim(rtrim((string) $row->{$field}, '0'), '.') : '') }}"@if ($field === 'recovery_pct') placeholder="100"@endif></td>
                    @endforeach
                </tr>
                @error("chemistry.{$key}.nominal_pct")<tr><td colspan="5"><div class="error">{{ $message }}</div></td></tr>@enderror
            @endforeach
            </tbody>
        </table></div>
        <div class="help">Recovery is how much of the element ends up in the iron: below 100 for what burns off (silicon, manganese), above 100 for what the melt picks up. Blank means 100. The lowest and highest figures widen the prediction; blank uses the typical value.</div>
    </div>
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
