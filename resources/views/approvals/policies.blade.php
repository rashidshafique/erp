@extends('layouts.app')
@section('title', 'Approval rules')
@section('content')
<div class="page-heading"><div><h1>Approval rules</h1><p>Switch an action on to require sign-off before it can happen. Segregation of duties: the person who raised a request cannot approve it (unless allowed here) and nobody can approve twice.</p></div></div>
<form method="POST" action="{{ route('approval-policies.update') }}">@csrf @method('PUT')
<div class="panel"><div class="table-wrap"><table><thead><tr><th>Action</th><th>Needs approval</th><th>Approvals needed</th><th>Who can approve</th><th>Requester may approve own</th></tr></thead><tbody>
@foreach($actions as $key => $label)@php($p = $policies[$key] ?? null)<tr><td>{{ $label }}<div class="muted small">{{ $key }}</div></td>
<td><input type="checkbox" name="p[{{ $key }}][is_active]" value="1" @checked($p?->is_active)></td>
<td><input type="number" min="1" max="5" name="p[{{ $key }}][required_approvals]" value="{{ $p?->required_approvals ?? 1 }}" style="width:70px"></td>
<td><select name="p[{{ $key }}][permission]">@foreach($permissions as $pk => $pl)<option value="{{ $pk }}" @selected(($p?->permission ?? 'approvals.decide') === $pk)>{{ $pl }}</option>@endforeach</select></td>
<td><input type="checkbox" name="p[{{ $key }}][allow_self_approval]" value="1" @checked($p?->allow_self_approval)></td></tr>@endforeach
</tbody></table></div><div class="panel-body"><button class="btn btn-primary">Save</button></div></div></form>
@endsection
