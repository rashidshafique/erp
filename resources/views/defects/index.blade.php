@extends('layouts.app')
@section('title', 'Defect list')
@section('content')
<div class="page-heading"><div><h1>Defect list</h1><p>Defects offered on inspections and NCRs. The ICF group follows the international casting-defect classification; check it against the atlas for your customers' wording.</p></div></div>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-error">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
<div class="panel"><div class="table-wrap"><table><thead><tr><th>Code</th><th>Name</th><th>Category</th><th>ICF group</th><th>Severity</th><th>Active</th><th></th></tr></thead><tbody>
@foreach($defects as $d)
<form method="POST" action="{{ route('defects.update', $d) }}">@csrf @method('PUT')<input type="hidden" name="inspection_guidance" value="{{ $d->inspection_guidance }}">
<tr><td><input name="code" value="{{ $d->code }}" size="14"></td><td><input name="name" value="{{ $d->name }}"></td>
<td><select name="category">@foreach($categories as $c)<option @selected($d->category===$c)>{{ $c }}</option>@endforeach</select></td>
<td><select name="icf_group"><option value="">—</option>@foreach($groups as $k => $l)<option value="{{ $k }}" @selected($d->icf_group===$k)>{{ $k }} — {{ $l }}</option>@endforeach</select></td>
<td><select name="default_severity">@foreach(['MINOR','MAJOR','CRITICAL'] as $s)<option @selected($d->default_severity===$s)>{{ $s }}</option>@endforeach</select></td>
<td><input type="checkbox" name="is_active" value="1" @checked($d->is_active)></td><td><button class="btn">Save</button></td></tr></form>
@endforeach</tbody></table></div>
<div class="panel-body"><h3 style="font-size:15px">Add a defect</h3><form method="POST" action="{{ route('defects.store') }}" class="form-grid">@csrf
<label>Code<input name="code" required></label><label>Name<input name="name" required></label>
<label>Category<select name="category">@foreach($categories as $c)<option>{{ $c }}</option>@endforeach</select></label>
<label>ICF group<select name="icf_group"><option value="">—</option>@foreach($groups as $k => $l)<option value="{{ $k }}">{{ $k }} — {{ $l }}</option>@endforeach</select></label>
<label>Severity<select name="default_severity">@foreach(['MINOR','MAJOR','CRITICAL'] as $s)<option>{{ $s }}</option>@endforeach</select></label>
<div><button class="btn btn-primary">Add</button></div></form></div></div>
@endsection
