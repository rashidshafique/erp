@extends('layouts.app')
@section('title', 'Pack lots')
@section('content')
<div class="page-heading"><div><h1>Pack lots</h1><p>Only READY lots with good, unpacked pieces appear here.</p></div></div>
<form method="POST" action="{{ route('packing.store') }}">@csrf
<div class="panel"><div class="table-wrap"><table><thead><tr><th>Lot</th><th>Product</th><th>Good</th><th>Left to pack</th><th>Pack now <label class="muted small" style="font-weight:400"><input type="checkbox" id="fill-all"> all</label></th><th>Weighed kg <span class="muted small" style="font-weight:400">(optional)</span></th></tr></thead><tbody>
@forelse($lots as $l)<tr><td>{{ $l->label }}</td><td>{{ $l->product?->code }}</td><td>{{ $l->quantity_good }}</td><td>{{ $l->packable }}</td><td><input type="number" name="qty[{{ $l->id }}]" min="0" max="{{ $l->packable }}" value="{{ old('qty.'.$l->id, 0) }}" data-each-kg="{{ round($l->kg_each, 4) }}"></td><td><input type="number" name="kg[{{ $l->id }}]" min="0" step="any" value="{{ old('kg.'.$l->id) }}" aria-label="Weighed kg for {{ $l->label }}" style="width:110px"></td></tr>
@empty<tr><td colspan="6" class="muted">No lot is ready for packing.</td></tr>@endforelse</tbody></table></div>
<div class="panel-body form-grid"><label>Packed by<input name="packed_by"></label><label>Checked by<input name="checked_by"></label><label>Package type<input name="package_type" placeholder="pallet, crate"></label><label>Store at (rack / bin)<input name="storage_location" maxlength="60"></label><label>Packages<input type="number" name="package_count" min="1" value="1"></label><label>Notes<input name="packaging_notes"></label>
<div><button class="btn btn-primary">Create packing record</button></div></div></div></form>
<script>
// The weighed kg box shows the estimate from the lot's poured weight; left empty, that estimate is saved. Lines sold by kg are invoiced on it.
function estimate(i) { var kg = i.closest('tr').querySelector('input[name^="kg["]'); if (kg) kg.placeholder = (+i.value && +i.dataset.eachKg) ? '≈ ' + (i.value * i.dataset.eachKg).toFixed(1) : ''; }
document.querySelectorAll('input[name^="qty["]').forEach(i => { estimate(i); i.addEventListener('input', () => estimate(i)); });
document.getElementById('fill-all')?.addEventListener('change', e => {
    document.querySelectorAll('input[type=number][max][name^="qty["]').forEach(i => { i.value = e.target.checked ? i.max : 0; estimate(i); });
});
</script>
@endsection
