@extends('layouts.app')
@section('title', 'Audit trail')
@section('content')
<div class="page-heading"><div><h1>Audit trail</h1><p>Every change made in the system, who made it and when. Entries cannot be edited or deleted; run <code>php artisan audit:verify</code> to check the chain.</p></div></div>
<div class="panel"><div class="panel-body"><form method="GET" style="display:flex;gap:10px;flex-wrap:wrap">
<select name="entity_type"><option value="">All records</option>@foreach($types as $t)<option @selected(request('entity_type')===$t)>{{ $t }}</option>@endforeach</select>
<select name="action"><option value="">All actions</option>@foreach(['CREATED','UPDATED','DELETED'] as $a)<option @selected(request('action')===$a)>{{ $a }}</option>@endforeach</select>
<input name="q" value="{{ request('q') }}" placeholder="Record number or name"><input name="user" value="{{ request('user') }}" placeholder="User">
<input type="date" name="from" value="{{ request('from') }}"><input type="date" name="to" value="{{ request('to') }}"><button class="btn">Filter</button></form></div></div>
<div class="panel"><div class="table-wrap"><table><thead><tr><th>When</th><th>Who</th><th>Action</th><th>Record</th><th>Changes</th></tr></thead><tbody>
@forelse($entries as $e)<tr><td>{{ $e->occurred_at->setTimezone(config('app.timezone'))->format('d M Y H:i:s') }}</td><td>{{ $e->user_name ?? '—' }}<div class="muted small">{{ $e->ip_address }}</div></td><td>{{ $e->action }}</td>
<td>{{ $e->entity_type }}<div class="small">{{ $e->entity_label }}</div></td><td>@include('audit._changes', ['entry' => $e])</td></tr>
@empty<tr><td colspan="5" class="muted">No entries match.</td></tr>@endforelse</tbody></table></div><div class="panel-body">{{ $entries->links() }}</div></div>
@endsection
