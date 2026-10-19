@extends('layouts.app')
@section('title', 'Nonconformance reports')
@section('content')
<div class="page-heading"><div><h1>Nonconformance reports</h1><p>Open NCRs block dispatch of the lot.</p></div></div>
@if(session("success"))<div class="alert alert-success">{{ session("success") }}</div>@endif
<div class="panel"><div class="table-wrap"><table><thead><tr><th>NCR</th><th>Lot</th><th>Title</th><th>Severity</th><th>Qty</th><th>Status</th></tr></thead><tbody>
@forelse($ncrs as $n)<tr><td><a href="{{ route('ncrs.show', $n) }}">{{ $n->ncr_number }}</a></td><td>{{ $n->lot?->lot_number }}</td><td>{{ $n->title }}</td><td>{{ $n->severity }}</td><td>{{ $n->quantity_affected }}</td><td>{{ $n->status }}</td></tr>
@empty<tr><td colspan="6" class="muted">No NCRs.</td></tr>@endforelse</tbody></table></div><div class="panel-body">{{ $ncrs->links() }}</div></div>
@endsection
