@extends('layouts.app')
@section('title', 'Inspections')
@section('content')
<div class="page-heading"><div><h1>Inspections</h1><p>Start an inspection from a casting lot.</p></div><div class="actions"><a class="btn" href="{{ route('lots.index') }}">Casting lots</a></div></div>
@if ($awaiting->isNotEmpty())
<div class="panel"><div class="panel-header"><h2>Waiting for inspection</h2><span class="muted small">{{ $awaiting->count() }} {{ $awaiting->count() === 1 ? 'lot' : 'lots' }} through their last stage</span></div><div class="table-wrap"><table><thead><tr><th>Lot</th><th>Product</th><th>Good pieces</th><th></th></tr></thead><tbody>
@foreach ($awaiting as $l)<tr><td><a href="{{ route('lots.show', $l) }}">{{ $l->label }}</a></td><td>{{ $l->product?->code }} {{ $l->product?->name }}</td><td>{{ $l->quantity_good }}</td><td><a class="btn btn-small btn-primary" href="{{ route('lots.inspections.create', $l) }}">Inspect</a></td></tr>@endforeach
</tbody></table></div></div>
@endif
<div class="panel"><div class="table-wrap"><table><thead><tr><th>Number</th><th>Lot</th><th>Type</th><th>Status</th><th>Inspected</th><th>Accepted</th><th>Rejected</th></tr></thead><tbody>
@forelse($inspections as $i)<tr><td><a href="{{ route('inspections.show', $i) }}">{{ $i->inspection_number }}</a></td><td>{{ $i->lot?->label }}</td><td>{{ $i->inspection_type }}</td><td>{{ $i->status }}</td><td>{{ $i->quantity_inspected }}</td><td>{{ $i->quantity_accepted }}</td><td>{{ $i->quantity_rejected }}</td></tr>
@empty<tr><td colspan="7" class="muted">No inspections yet.</td></tr>@endforelse</tbody></table></div><div class="panel-body">{{ $inspections->links() }}</div></div>
@endsection
