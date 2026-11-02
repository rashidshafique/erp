@extends('layouts.app')
@section('title', 'Mould readiness')
@section('content')
<div class="page-heading"><div><h1>Mould readiness</h1><p>Moulds waiting for metal, by iron grade. Use it to time the melt.</p></div></div>
<div class="panel"><div class="table-wrap"><table><thead><tr><th>Grade</th><th>Orders</th><th>Accepted, not assembled</th><th>Assembled, ready to pour</th><th>Castings</th><th>Metal needed kg</th>@foreach($furnaces as $f)<th>{{ $f->code }} heats<div class="muted small">{{ rtrim(rtrim($f->rated_capacity_kg,'0'),'.') }} kg</div></th>@endforeach<th>Oldest mould</th></tr></thead><tbody>
@forelse($groups as $g)@php $age = $g['oldest'] ? $g['oldest']->diffInHours(now()) : null; @endphp
<tr><td><strong>{{ $g['label'] }}</strong></td><td class="small">{{ implode(', ', array_keys($g['orders'])) }}</td><td>{{ $g['unassembled'] }}</td><td>{{ $g['ready'] }}</td><td>{{ $g['castings'] }}</td><td>{{ \App\Support\Formats::number($g['metal_kg'], 0) }}</td>
@foreach($furnaces as $f)<td>{{ $g['metal_kg'] > 0 ? ceil($g['metal_kg'] / (float) $f->rated_capacity_kg) : '—' }}</td>@endforeach
<td>@if($age !== null)<span class="{{ $age > $staleHours ? 'status status-cancelled' : '' }}">{{ $age }} h</span>@if($age > $staleHours) <span class="small">pour soon: sand is ageing</span>@endif @else — @endif</td></tr>
@empty<tr><td colspan="{{ 7 + $furnaces->count() }}" class="muted">No moulds are waiting.</td></tr>@endforelse</tbody></table></div>
<div class="panel-body muted small">Metal needed is the poured weight from each cavity map. Allow for melt loss and ladle remnant. Unassembled moulds have no weight yet.</div></div>
@endsection
