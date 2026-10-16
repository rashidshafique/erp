@extends('layouts.app')
@section('title', 'Roles and permissions')
@section('content')
<div class="page-heading"><div><h1>Roles and permissions</h1><p>Administrators always have everything. Any signed-in user can read pages; permissions control who can change things.</p></div></div>
<form method="POST" action="{{ route('roles.update') }}">@csrf @method('PUT')
<div class="panel"><div class="table-wrap"><table><thead><tr><th>Permission</th>@foreach($roles as $r)<th>{{ $r->name }}</th>@endforeach</tr></thead><tbody>
@foreach($catalog as $key => $label)<tr><td>{{ $label }}<div class="muted small">{{ $key }}</div></td>
@foreach($roles as $r)<td style="text-align:center">@if($r->code === 'ADMIN')✔@else<input type="checkbox" name="perm[{{ $r->id }}][{{ $key }}]" value="1" @checked(in_array($key, $granted[$r->id] ?? []))>@endif</td>@endforeach</tr>@endforeach
</tbody></table></div><div class="panel-body"><button class="btn btn-primary">Save permissions</button></div></div></form>
@endsection
