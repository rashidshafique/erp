@extends('layouts.app')
@section('title', 'Casting lots')
@section('content')
<div class="page-heading"><div><h1>Casting lots</h1><p>Every casting traced from pour to dispatch.</p></div></div>
@if($firstIronHeld > 0)
<div class="panel"><div class="panel-body" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap"><span>{{ $firstIronHeld }} lot(s) are on hold only because they were first iron.</span>
<form method="POST" action="{{ route('lots.release-first-iron') }}" onsubmit="return confirm('Release {{ $firstIronHeld }} first-iron lot(s) from hold?')">@csrf<button class="btn btn-primary">Release first-iron holds</button></form></div></div>
@endif
<div class="panel"><div class="panel-body">
<form method="GET" style="display:flex;gap:10px;flex-wrap:wrap">
<input name="search" value="{{ request('search') }}" placeholder="Lot number">
<select name="status"><option value="">All statuses</option>@foreach(['IN_PRODUCTION','READY','PACKED','DISPATCHED','ON_HOLD','SCRAPPED','CLOSED'] as $s)<option value="{{ $s }}" @selected(request('status')===$s)>{{ $s }} ({{ $counts[$s] ?? 0 }})</option>@endforeach</select>
<button class="btn">Filter</button></form></div>
<form method="POST" action="{{ route('lots.bulk') }}" id="bulk-form">@csrf
<input type="hidden" name="status" value="{{ request('status') }}"><input type="hidden" name="search" value="{{ request('search') }}">
<div class="panel-body" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
<select name="action" aria-label="Action" id="bulk-action"><option value="release">Release from hold</option><option value="hold">Put on hold</option><option value="combine">Combine into one lot</option></select>
<input name="reason" placeholder="Reason (needed to hold)" maxlength="500">
<label id="all-matching-wrap" hidden><input type="checkbox" name="all_matching" value="1"> All {{ $lots->total() }} lot(s) matching this filter</label>
<button class="btn" type="submit" onclick="return confirm(document.getElementById('bulk-action').value === 'combine' ? 'Combine the selected lots? Lots of the same heat, order, status and stages passed become one lot, kept under the oldest code. This cannot be undone.' : 'Change the status of the selected lots?')">Apply to selected</button></div>
<div class="table-wrap"><table><thead><tr><th><input type="checkbox" id="select-all" aria-label="Select all lots on this page"></th><th>Lot</th><th>Product</th><th>Heat</th><th>Order</th><th>Poured</th><th>Good</th><th>Rejected</th><th>Status</th><th>Stages</th></tr></thead><tbody>
@forelse($lots as $l)
<tr><td><input type="checkbox" name="lots[]" value="{{ $l->id }}" class="lot-box" aria-label="Select {{ $l->label }}"></td><td><a href="{{ route('lots.show', $l) }}">{{ $l->label }}</a></td><td><x-product-cell :product="$l->product" /></td><td>{{ $l->pourMouldAssembly?->pour?->heat?->heat_number ?? '—' }}</td><td>{{ $l->productionOrder?->production_order_number ?? '—' }}</td><td>{{ $l->quantity_poured }}</td><td>{{ $l->quantity_good }}</td><td>{{ $l->quantity_rejected }}</td><td><span class="status">{{ $l->status }}</span></td><td class="muted small" style="min-width:200px;white-space:normal">{{ $l->stages_summary }}</td></tr>
@empty<tr><td colspan="10" class="muted">No lots yet. Lots are created when a pour with assigned moulds is completed.</td></tr>@endforelse
</tbody></table></div></form>
<script>
document.getElementById('select-all')?.addEventListener('change', e => {
    document.querySelectorAll('.lot-box').forEach(b => b.checked = e.target.checked);
    document.getElementById('all-matching-wrap').hidden = ! e.target.checked || {{ $lots->hasPages() ? 'false' : 'true' }};
});
</script>
<div class="panel-body">{{ $lots->links() }}</div></div>
@endsection
