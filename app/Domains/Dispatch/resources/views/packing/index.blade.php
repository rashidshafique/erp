@extends('layouts.app')
@section('title', 'Packing')
@section('content')
<div class="page-heading"><div><h1>Packing</h1><p>Pack only lots that are cleared.</p></div><div class="actions"><a class="btn btn-primary" href="{{ route('packing.create') }}">+ Pack lots</a></div></div>
@if(session("success"))<div class="alert alert-success">{{ session("success") }}</div>@endif
@if($errors->any())<div class="alert alert-error">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
<div class="panel"><div class="table-wrap"><table><thead><tr><th>Record</th><th>Packed</th><th>Lots</th><th>Net kg</th><th>Packed by</th></tr></thead><tbody>
@forelse($records as $r)<tr><td><a href="{{ route('packing.show', $r) }}">{{ $r->packing_number }}</a></td><td>{{ $r->packed_at?->displayShortDateTime() }}</td><td>{{ $r->items_count }}</td><td>{{ $r->total_net_weight_kg }}</td><td>{{ $r->packed_by }}</td></tr>
@empty<tr><td colspan="5" class="muted">Nothing packed yet.</td></tr>@endforelse</tbody></table></div><div class="panel-body">{{ $records->links() }}</div></div>
@endsection
