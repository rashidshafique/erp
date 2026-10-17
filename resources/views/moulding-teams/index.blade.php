@extends('layouts.app')
@section('title', 'Teams and stations')
@section('content')
<div class="page-heading"><div><h1>Moulding teams and stations</h1><p>Each team has one lead who answers for its work. Each moulding line has its own team (work lists use it automatically, nobody picks a team there) and the number of patterns it moulds at once: an automated line is always one, a manual bench can be several. Output below is the last 30 days.</p></div>
<div class="actions"><button type="button" class="btn" data-quick="qd-team">+ Add team</button><button type="button" class="btn btn-primary" data-quick="qd-station">+ Add station</button></div></div>
@include('partials.quick-dialog', ['id' => 'qd-team', 'title' => 'Add a team', 'action' => route('moulding-teams.teams.store'), 'submit' => 'Add team', 'fields' => 'moulding-teams._quick-team'])
@include('partials.quick-dialog', ['id' => 'qd-station', 'title' => 'Add a station', 'action' => route('moulding-teams.stations.store'), 'submit' => 'Add station', 'fields' => 'moulding-teams._quick-station'])
@include('partials.quick-dialog', ['id' => 'qd-sand', 'title' => 'Add a sand system', 'action' => route('sand-systems.store'), 'submit' => 'Add sand system', 'fields' => 'sand-systems._quick-fields'])
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

<div class="panel"><div class="panel-header"><h2>Stations</h2></div><div class="table-wrap"><table><thead><tr><th>Code</th><th>Name</th><th>Type</th><th>Patterns at once</th><th>Team on this line</th><th>Sand system</th><th>Flasks</th><th>Status</th><th>Runs</th><th>Made</th><th>Damaged %</th><th>Downtime min</th><th></th></tr></thead><tbody>
@foreach($stations as $st)@php $s = $stationStats[$st->id] ?? null; @endphp
<form method="POST" action="{{ route('moulding-teams.stations.update', $st) }}">@csrf @method('PUT')
<tr><td><input name="code" value="{{ $st->code }}" size="8"></td><td><input name="name" value="{{ $st->name }}"></td>
<td><select name="station_type">@foreach($stationTypes as $k => $l)<option value="{{ $k }}" @selected($st->station_type===$k)>{{ $l }}</option>@endforeach</select></td>
<td>@if($st->isAutomated())1 <span class="muted small">(line)</span><input type="hidden" name="max_patterns" value="1">@else<input type="number" name="max_patterns" min="1" max="20" value="{{ $st->max_patterns }}" style="width:64px">@endif</td>
<td><select name="default_moulding_team_id" data-quick-list="team"><option value="">—</option>@foreach($teams->where('status','ACTIVE') as $t)<option value="{{ $t->id }}" @selected($st->default_moulding_team_id===$t->id)>{{ $t->code }} — {{ $t->lead_name }}</option>@endforeach</select></td>
<td><select name="default_sand_system" data-quick-list="sand"><option value="">—</option>@foreach(\App\Models\SandSystem::choices($st->default_sand_system) as $sd)<option @selected($st->default_sand_system===$sd)>{{ $sd }}</option>@endforeach</select></td>
<td class="small"><details><summary style="cursor:pointer">{{ $st->makes_flaskless ? 'Flaskless' : ($st->flaskSizes->isEmpty() ? 'Any size' : $st->flaskSizes->pluck('name')->implode(', ')) }}</summary>
<input type="hidden" name="flask_sizes_sent" value="1"><label style="display:flex;gap:6px;align-items:center;white-space:nowrap"><input type="checkbox" name="makes_flaskless" value="1" @checked($st->makes_flaskless)> Makes flaskless moulds</label>
@foreach($flaskSizes as $fs)<label style="display:flex;gap:6px;align-items:center;white-space:nowrap"><input type="checkbox" name="flask_size_ids[]" value="{{ $fs->id }}" @checked($st->flaskSizes->contains('id', $fs->id))> {{ $fs->name }}</label>@endforeach
@if($flaskSizes->isEmpty())<span class="muted">No flask sizes yet. <a href="{{ route('flasks.index') }}">Add them on the flask board.</a></span>@endif</details></td>
<td><select name="status">@foreach(['ACTIVE','MAINTENANCE','INACTIVE'] as $x)<option @selected($st->status===$x)>{{ $x }}</option>@endforeach</select></td>
<td>{{ $s->runs ?? 0 }}</td><td>{{ $s->made ?? 0 }}</td><td>{{ ($s->made ?? 0) > 0 ? round(100 * $s->damaged / $s->made, 1) : '—' }}</td><td>{{ $s->downtime ?? 0 }}</td><td><button class="btn">Save</button></td></tr></form>
@endforeach</tbody></table></div>
<div class="panel-body"><form method="POST" action="{{ route('moulding-teams.stations.store') }}" class="form-grid">@csrf
<label>Code<input name="code" required placeholder="MP-1"></label><label>Name<input name="name" required></label>
<label>Type<select name="station_type">@foreach($stationTypes as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
<label>Patterns at once<input type="number" name="max_patterns" min="1" max="20" value="4" title="An automated line is always one"></label>
<label>Team on this line <a href="{{ route('moulding-teams.index') }}" class="qd-add" data-quick="qd-team" data-quick-target="ns-team">+ Add</a><select name="default_moulding_team_id" id="ns-team" data-quick-list="team"><option value="">—</option>@foreach($teams->where('status','ACTIVE') as $t)<option value="{{ $t->id }}">{{ $t->code }} — {{ $t->lead_name }}</option>@endforeach</select></label><label>Sand system <a href="{{ route('sand-systems.index') }}" class="qd-add" data-quick="qd-sand" data-quick-target="ns-sand" data-quick-value="label">+ Add</a><select name="default_sand_system" id="ns-sand" data-quick-list="sand"><option value="">—</option>@foreach($sands as $sd)<option>{{ $sd }}</option>@endforeach</select></label><input type="hidden" name="status" value="ACTIVE">
<input type="hidden" name="flask_sizes_sent" value="1"><label style="display:flex;gap:6px;align-items:center"><input type="checkbox" name="makes_flaskless" value="1"> Makes flaskless moulds (DISAMATIC, DISA Match)</label>
@if($flaskSizes->isNotEmpty())<label>Flask sizes it handles<select name="flask_size_ids[]" multiple size="{{ min(4, $flaskSizes->count()) }}">@foreach($flaskSizes as $fs)<option value="{{ $fs->id }}">{{ $fs->name }}</option>@endforeach</select></label>@endif
<div><button class="btn btn-primary">+ Add station</button></div></form></div></div>

<p class="muted small">The sand systems offered here are set under Production → <a href="{{ route('sand-systems.index') }}">Sand systems</a>.</p>
@endsection
