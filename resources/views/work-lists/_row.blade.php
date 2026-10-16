{{-- One pattern plate row: pattern, optional order, mould count. $i is the row index ('__i__' in the template). --}}
<tr class="aw-row">
    <td><select name="rows[{{ $i }}][pattern_revision_id]" class="aw-pattern" style="width:280px" required>
        @foreach($patterns as $p)<option value="{{ $p->id }}" data-products="{{ implode(',', $patternProducts[$p->id] ?? []) }}" @selected(($row['pattern_revision_id'] ?? null) === $p->id)>{{ $p->pattern->code }} rev {{ $p->revision }} ({{ $p->pattern->pattern_type }})</option>@endforeach
    </select>
    <div class="aw-sandwarn" hidden style="color:var(--danger,#b42318);font-size:12px;margin-top:3px;width:280px;white-space:normal"><span class="aw-sandtext"></span>
        <label style="font-weight:600;display:flex;gap:6px;align-items:center"><input type="checkbox" class="aw-any" name="rows[{{ $i }}][sand_override]" value="1" @checked(! empty($row['sand_override']))> Use anyway</label></div></td>
    <td><select name="rows[{{ $i }}][production_order_id]" class="aw-order" style="width:280px">
        <option value="">Automatic: the open order for this plate</option>
        @foreach($orders as $o)<option value="{{ $o->id }}" data-product="{{ $o->product_id }}" @selected(($row['production_order_id'] ?? null) === $o->id)>{{ $o->production_order_number }} · {{ $o->customerLabel() }} · {{ $o->product?->code }}</option>@endforeach
    </select><div class="muted small aw-preview"></div></td>
    <td><input type="number" name="rows[{{ $i }}][planned_moulds]" min="1" max="5000" value="{{ $row['planned_moulds'] ?? 10 }}" style="width:90px" required></td>
    <td><button type="button" class="btn btn-small aw-del" title="Remove this row" aria-label="Remove this row">✕</button></td>
</tr>
