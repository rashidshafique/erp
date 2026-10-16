@extends('layouts.app')
@section('title', 'Teams and stations')
@section('content')
<div class="page-heading"><div><h1>Moulding teams and stations</h1><p>Each team has one lead who answers for its work. Each moulding line has its own team (work lists use it automatically, nobody picks a team there) and the number of patterns it moulds at once: an automated line is always one, a manual bench can be several. Output below is the last 30 days.</p></div></div>
@if(session("success"))<div class="alert alert-success">{{ session("success") }}</div>@endif
@if($errors->any())<div class="alert alert-error">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
<div class="panel"><div class="panel-header"><h2>Teams</h2></div><div class="table-wrap"><table><thead><tr><th>Code</th><th>Name</th><th>Lead</th><th>Phone</th><th>Members</th><th>Status</th><th>Runs</th><th>Made</th><th>Accepted</th><th>Damaged %</th><th>Downtime min</th><th></th></tr></thead><tbody>
@foreach($teams as $t)@php $s = $teamStats[$t->id] ?? null; @endphp
<form method="POST" action="{{ route('moulding-teams.teams.update', $t) }}">@csrf @method('PUT')
<tr><td><input name="code" value="{{ $t->code }}" size="8"></td><td><input name="name" value="{{ $t->name }}"></td><td><input name="lead_name" value="{{ $t->lead_name }}"></td><td><input name="lead_phone" value="{{ $t->lead_phone }}" size="12"></td><td><input type="number" name="member_count" min="1" value="{{ $t->member_count }}" style="width:70px"></td>
<td><select name="status"><option @selected($t->status==='ACTIVE')>ACTIVE</option><option @selected($t->status==='INACTIVE')>INACTIVE</option></select></td>
<td>{{ $s->runs ?? 0 }}</td><td>{{ $s->made ?? 0 }}</td><td>{{ $s->accepted ?? 0 }}</td><td>{{ ($s->made ?? 0) > 0 ? round(100 * $s->damaged / $s->made, 1) : '—' }}</td><td>{{ $s->downtime ?? 0 }}</td><td><button class="btn">Save</button></td></tr></form>
@endforeach</tbody></table></div>
<div class="panel-body"><form method="POST" action="{{ route('moulding-teams.teams.store') }}" class="form-grid">@csrf
<label>Code<input name="code" required placeholder="T1"></label><label>Team name<input name="name" required></label><label>Team lead<input name="lead_name" required></label><label>Lead phone<input name="lead_phone"></label><label>Members<input type="number" name="member_count" min="1" value="4" required></label><input type="hidden" name="status" value="ACTIVE">
<div><button class="btn btn-primary">+ Add team</button></div></form></div></div>

<div class="panel"><div class="panel-header"><h2>Stations</h2></div><div class="table-wrap"><table><thead><tr><th>Code</th><th>Name</th><th>Type</th><th>Patterns at once</th><th>Team on this line</th><th>Sand system</th><th>Status</th><th>Runs</th><th>Made</th><th>Damaged %</th><th>Downtime min</th><th></th></tr></thead><tbody>
@foreach($stations as $st)@php $s = $stationStats[$st->id] ?? null; @endphp
<form method="POST" action="{{ route('moulding-teams.stations.update', $st) }}">@csrf @method('PUT')
<tr><td><input name="code" value="{{ $st->code }}" size="8"></td><td><input name="name" value="{{ $st->name }}"></td>
<td><select name="station_type">@foreach($stationTypes as $k => $l)<option value="{{ $k }}" @selected($st->station_type===$k)>{{ $l }}</option>@endforeach</select></td>
<td>@if($st->isAutomated())1 <span class="muted small">(line)</span><input type="hidden" name="max_patterns" value="1">@else<input type="number" name="max_patterns" min="1" max="20" value="{{ $st->max_patterns }}" style="width:64px">@endif</td>
<td><select name="default_moulding_team_id"><option value="">—</option>@foreach($teams->where('status','ACTIVE') as $t)<option value="{{ $t->id }}" @selected($st->default_moulding_team_id===$t->id)>{{ $t->code }} — {{ $t->lead_name }}</option>@endforeach</select></td>
<td><select name="default_sand_system"><option value="">—</option>@foreach(\App\Models\SandSystem::choices($st->default_sand_system) as $sd)<option @selected($st->default_sand_system===$sd)>{{ $sd }}</option>@endforeach</select></td>
<td><select name="status">@foreach(['ACTIVE','MAINTENANCE','INACTIVE'] as $x)<option @selected($st->status===$x)>{{ $x }}</option>@endforeach</select></td>
<td>{{ $s->runs ?? 0 }}</td><td>{{ $s->made ?? 0 }}</td><td>{{ ($s->made ?? 0) > 0 ? round(100 * $s->damaged / $s->made, 1) : '—' }}</td><td>{{ $s->downtime ?? 0 }}</td><td><button class="btn">Save</button></td></tr></form>
@endforeach</tbody></table></div>
<div class="panel-body"><form method="POST" action="{{ route('moulding-teams.stations.store') }}" class="form-grid">@csrf
<label>Code<input name="code" required placeholder="MP-1"></label><label>Name<input name="name" required></label>
<label>Type<select name="station_type">@foreach($stationTypes as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
<label>Patterns at once<input type="number" name="max_patterns" min="1" max="20" value="4" title="An automated line is always one"></label>
<label>Team on this line<select name="default_moulding_team_id"><option value="">—</option>@foreach($teams->where('status','ACTIVE') as $t)<option value="{{ $t->id }}">{{ $t->code }} — {{ $t->lead_name }}</option>@endforeach</select></label><label>Sand system<select name="default_sand_system"><option value="">—</option>@foreach($sands as $sd)<option>{{ $sd }}</option>@endforeach</select></label><input type="hidden" name="status" value="ACTIVE">
<div><button class="btn btn-primary">+ Add station</button></div></form></div></div>

<div class="panel" id="sand-systems"><div class="panel-header"><h2>Sand systems</h2><span class="muted small">The sands offered on patterns, stations and work lists, in this order. The first active one is the default for a station with none set.</span></div><div class="table-wrap"><table><thead><tr><th>Order</th><th>Name</th><th>Type</th><th>Description</th><th>Status</th><th>Used by</th><th></th></tr></thead><tbody>
@foreach($sandSystems as $sd)@php $used = $sandUsage[$sd->id] ?? []; @endphp
<form method="POST" action="{{ route('moulding-teams.sands.update', $sd) }}">@csrf @method('PUT')
<tr><td><input type="number" name="sort_order" min="0" value="{{ $sd->sort_order }}" style="width:70px"></td><td><input name="name" value="{{ $sd->name }}" required maxlength="100"></td>
<td><select name="system_type">@foreach($sandTypes as $k => $l)<option value="{{ $k }}" @selected($sd->system_type===$k)>{{ $l }}</option>@endforeach</select></td>
<td><input name="description" value="{{ $sd->description }}" maxlength="1000"></td>
<td><select name="status"><option @selected($sd->status==='ACTIVE')>ACTIVE</option><option @selected($sd->status==='INACTIVE')>INACTIVE</option></select></td>
<td class="small">{{ $used ? collect($used)->map(fn ($n, $what) => "$n $what")->implode(', ') : '—' }}</td>
<td style="white-space:nowrap"><button class="btn">Save</button>@unless($used) <button class="btn btn-danger" form="del-sand-{{ $sd->id }}" onclick="return confirm('Delete {{ $sd->name }}?')">Delete</button>@endunless</td></tr></form>
@endforeach</tbody></table></div>
@foreach($sandSystems as $sd)<form id="del-sand-{{ $sd->id }}" method="POST" action="{{ route('moulding-teams.sands.destroy', $sd) }}">@csrf @method('DELETE')</form>@endforeach
<div class="panel-body"><form method="POST" action="{{ route('moulding-teams.sands.store') }}" class="form-grid">@csrf
<label>Name<input name="name" required maxlength="100" placeholder="e.g. No-bake"></label>
<label>Type<select name="system_type">@foreach($sandTypes as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
<label>Description<input name="description" maxlength="1000"></label>
<div><button class="btn btn-primary">+ Add sand system</button></div></form>
<p class="muted small">Renaming a sand changes it everywhere it is recorded. A sand in use cannot be deleted; set it to inactive to stop offering it while keeping the records that use it.</p></div></div>
@endsection
