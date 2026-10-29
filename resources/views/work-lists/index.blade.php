@extends('layouts.app')
@section('title', 'Moulding work lists')
@section('content')
<div class="page-heading"><div><h1>Moulding work lists</h1><p>The list given to the moulding teams each shift. <a href="{{ route('settings.work-lists.edit') }}">Rules</a></p></div></div>
@if(session("success"))<div class="alert alert-success">{{ session("success") }}</div>@endif
@if(session("warning"))<div class="alert alert-warning">{{ session("warning") }}</div>@endif
<div class="panel"><div class="panel-header"><h2>Prepare from orders</h2><span class="muted small">What open orders still need, spread over the stations, with unstarted rows of earlier lists carried over. Change it, then issue it.</span></div><div class="panel-body">
<form method="POST" action="{{ route('work-lists.prepare') }}" style="display:flex;gap:8px;align-items:center">@csrf
<select name="day" aria-label="Day"><option value="{{ today()->toDateString() }}">Today</option><option value="{{ today()->addDay()->toDateString() }}" selected>Tomorrow</option></select>
<button class="btn btn-primary">Prepare list</button></form></div></div>
<div class="panel"><div class="panel-header"><h2>New list</h2></div><div class="panel-body"><form method="POST" action="{{ route('work-lists.store') }}" class="form-grid">@csrf
<label>Date<input type="date" name="list_date" value="{{ today()->toDateString() }}" required></label>
<label>Shift<select name="shift">@foreach($shifts as $s)<option>{{ $s }}</option>@endforeach</select></label><label>Notes<input name="notes"></label>
<div><button class="btn btn-primary">Create list</button></div></form></div></div>
@if($errors->has('item'))<div class="alert alert-error">{{ $errors->first('item') }}</div>@endif
@if($rework->isNotEmpty())<div class="panel"><div class="panel-header"><h2>Remake needed</h2><span class="muted small">Completed runs that made fewer good moulds than planned</span></div><div class="table-wrap"><table><thead><tr><th>Run</th><th>Order / product</th><th>Pattern</th><th>Station</th><th>Planned</th><th>Accepted</th><th>Remake</th><th></th></tr></thead><tbody>
@foreach($rework as $r)@php $short = $r->planned_mould_qty - $r->accepted_mould_qty; @endphp<tr><td><a href="{{ route('moulding-runs.show', $r) }}">{{ $r->run_number }}</a></td><td>{{ $r->productionOrder?->production_order_number }}<div class="muted small">{{ $r->productionOrder?->product?->name }}</div></td><td>{{ $r->patternRevision?->pattern?->code ?? '—' }}</td><td>{{ $r->station?->code ?? '—' }}</td><td>{{ $r->planned_mould_qty }}</td><td>{{ $r->accepted_mould_qty }}</td><td><strong>{{ $short }}</strong> moulds</td>
<td>@if($openLists->isNotEmpty())<form method="POST" action="{{ route('work-lists.remake', $r) }}" style="display:flex;gap:6px">@csrf<select name="moulding_work_list_id">@foreach($openLists as $ol)<option value="{{ $ol->id }}">{{ $ol->list_number }} · {{ $ol->shift }} ({{ $ol->status }})</option>@endforeach</select><button class="btn btn-small">Add to list</button></form>@else<span class="muted small">Create a list first</span>@endif</td></tr>@endforeach</tbody></table></div></div>@endif
<div class="panel"><div class="table-wrap"><table><thead><tr><th>List</th><th>Date</th><th>Shift</th><th>Items</th><th>Status</th><th>Issued by</th></tr></thead><tbody>
@forelse($lists as $l)<tr><td><a href="{{ route('work-lists.show', $l) }}">{{ $l->list_number }}</a></td><td>{{ $l->list_date->displayDate() }}</td><td>{{ $l->shift }}</td><td>{{ $l->items_count }}</td><td><span class="status">{{ $l->status }}</span></td><td>{{ $l->issued_by }}</td></tr>
@empty<tr><td colspan="6" class="muted">No lists yet.</td></tr>@endforelse</tbody></table></div><div class="panel-body">{{ $lists->links() }}</div></div>
@endsection
