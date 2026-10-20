@extends('layouts.app')
@section('title', 'Moulding work lists')
@section('content')
<div class="page-heading"><div><h1>Moulding work lists</h1><p>The list given to the moulding teams each shift. <a href="{{ route('settings.work-lists.edit') }}">Rules</a></p></div></div>
@if(session("success"))<div class="alert alert-success">{{ session("success") }}</div>@endif
<div class="panel"><div class="panel-header"><h2>New list</h2></div><div class="panel-body"><form method="POST" action="{{ route('work-lists.store') }}" class="form-grid">@csrf
<label>Date<input type="date" name="list_date" value="{{ today()->toDateString() }}" required></label>
<label>Shift<select name="shift">@foreach($shifts as $s)<option>{{ $s }}</option>@endforeach</select></label><label>Notes<input name="notes"></label>
<div><button class="btn btn-primary">Create list</button></div></form></div></div>
<div class="panel"><div class="table-wrap"><table><thead><tr><th>List</th><th>Date</th><th>Shift</th><th>Items</th><th>Status</th><th>Issued by</th></tr></thead><tbody>
@forelse($lists as $l)<tr><td><a href="{{ route('work-lists.show', $l) }}">{{ $l->list_number }}</a></td><td>{{ $l->list_date->displayDate() }}</td><td>{{ $l->shift }}</td><td>{{ $l->items_count }}</td><td><span class="status">{{ $l->status }}</span></td><td>{{ $l->issued_by }}</td></tr>
@empty<tr><td colspan="6" class="muted">No lists yet.</td></tr>@endforelse</tbody></table></div><div class="panel-body">{{ $lists->links() }}</div></div>
@endsection
