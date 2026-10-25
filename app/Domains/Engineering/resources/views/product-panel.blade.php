{{-- The bill of materials of each revision of a product: the items used besides metal. $product. --}}
@php
    $canEdit = ! $product->trashed() && \App\Http\Middleware\EnforceAccess::allows(auth()->user(), 'bom.store');
    $materials = $canEdit ? \App\Models\Material::where('status', 'ACTIVE')->orderBy('name')->get(['id', 'code', 'name', 'base_uom']) : collect();
    $bases = \App\Domains\Engineering\Models\BomLine::BASES;
    $revisions = $product->revisions->load('bomLines.material');
@endphp
<div class="panel" id="bom">
    <div class="panel-header"><h2>Bill of materials</h2><span class="muted small">Items used besides the metal, which the planner works out from the cavity map. Per casting counts every piece, per mould counts each mould made, per kg poured follows the metal.</span></div>
    <div class="panel-body">
        @if ($errors->has('bom'))<div class="alert alert-error">{{ $errors->first('bom') }}</div>@endif
        @forelse ($revisions as $revision)
            <h3 style="margin:12px 0 6px">Revision {{ $revision->revision }}</h3>
            @if ($revision->bomLines->isEmpty())
                <p class="muted">No items listed.</p>
                @if ($canEdit && $revisions->count() > 1)
                    <form method="POST" action="{{ route('bom.copy', $revision) }}" style="display:flex;gap:8px;align-items:center;margin-bottom:8px">@csrf
                        <select name="from_revision_id" aria-label="Copy from revision">@foreach ($revisions->where('id', '!=', $revision->id)->filter(fn ($r) => $r->bomLines->isNotEmpty()) as $other)<option value="{{ $other->id }}">Revision {{ $other->revision }}</option>@endforeach</select>
                        <button class="btn btn-small" type="submit">Copy list</button>
                    </form>
                @endif
            @else
                <div class="table-wrap"><table>
                    <thead><tr><th>Item</th><th>Quantity</th><th>Basis</th><th>Notes</th>@if ($canEdit)<th></th>@endif</tr></thead>
                    <tbody>
                    @foreach ($revision->bomLines as $line)
                        <tr>
                            @if ($canEdit)
                                <td>{{ $line->material?->code }} — {{ $line->material?->name }}</td>
                                <td colspan="3"><form id="bom-{{ $line->id }}" method="POST" action="{{ route('bom.update', $line) }}" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">@csrf @method('PUT')
                                    <input name="quantity" type="number" step="any" min="0.0001" required value="{{ rtrim(rtrim((string) $line->quantity, '0'), '.') }}" style="width:110px" aria-label="Quantity"> <span class="muted small">{{ $line->material?->base_uom }}</span>
                                    <select name="basis" aria-label="Basis">@foreach ($bases as $key => $label)<option value="{{ $key }}" @selected($line->basis === $key)>{{ $label }}</option>@endforeach</select>
                                    <input name="notes" maxlength="300" value="{{ $line->notes }}" placeholder="Notes" style="flex:1;min-width:140px" aria-label="Notes">
                                    <button class="btn btn-small" type="submit">Save</button></form></td>
                                <td><form method="POST" action="{{ route('bom.destroy', $line) }}" onsubmit="return confirm('Remove this item from the list?')">@csrf @method('DELETE')<button class="btn btn-small" type="submit">Remove</button></form></td>
                            @else
                                <td>{{ $line->material?->code }} — {{ $line->material?->name }}</td>
                                <td>{{ rtrim(rtrim((string) $line->quantity, '0'), '.') }} {{ $line->material?->base_uom }}</td>
                                <td>{{ $bases[$line->basis] }}</td>
                                <td>{{ $line->notes ?: '—' }}</td>
                            @endif
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            @endif
            @if ($canEdit)
                <form method="POST" action="{{ route('bom.store', $revision) }}" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;margin:8px 0 16px">@csrf
                    <div class="field"><label>Add item</label><select name="material_id" data-search-select required><option value="">Select item</option>@foreach ($materials as $m)<option value="{{ $m->id }}" data-sub="{{ $m->base_uom }}">{{ $m->code }} — {{ $m->name }}</option>@endforeach</select></div>
                    <div class="field"><label>Quantity</label><input name="quantity" type="number" step="any" min="0.0001" required style="width:110px"></div>
                    <div class="field"><label>Basis</label><select name="basis">@foreach ($bases as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
                    <div class="field" style="flex:1;min-width:140px"><label>Notes</label><input name="notes" maxlength="300"></div>
                    <button class="btn btn-primary" type="submit">Add</button>
                </form>
            @endif
        @empty
            <p class="muted">Add a revision first.</p>
        @endforelse
        @if ($canEdit)@error('material_id')<div class="error-text">{{ $message }}</div>@enderror @error('quantity')<div class="error-text">{{ $message }}</div>@enderror @endif
    </div>
</div>
