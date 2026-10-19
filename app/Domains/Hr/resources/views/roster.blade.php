@extends('layouts.app')
@section('title', 'Roster & shifts')
@section('breadcrumb', 'Roster & shifts')
@section('content')
<div class="page-heading"><div><h1>Roster &amp; shifts</h1><p>Who works which shift, the shift times, and the holiday calendar.</p></div></div>
@if (session('error'))<div class="alert alert-error">{{ session('error') }}</div>@endif
@php $dep = request('department'); $tab = request('tab'); @endphp
<style>.row-edit summary { cursor: pointer; color: var(--accent); font-weight: 600; } .roster td, .roster th { white-space: nowrap; } .roster select { min-height: 28px; padding: 2px 4px; max-width: 150px; font-size: 12px; } .roster .dflt { font-size: 11px; color: var(--muted); }</style>

<div class="tab-marker" data-tab-start="Roster"></div>
<div class="panel"><div class="panel-body"><form method="GET" class="filters">
    <div class="field"><label for="week">Week of</label><input id="week" type="date" name="week" value="{{ $week->toDateString() }}"></div>
    <div class="field"><label for="department">Department</label><select id="department" name="department"><option value="">Everyone</option>@foreach ($departments as $x)<option value="{{ $x->id }}" @selected($dep === $x->id)>{{ $x->name }}</option>@endforeach</select></div>
    <button class="btn btn-primary">Show</button>
    <a class="btn" href="{{ route('hr.roster', ['week' => $week->copy()->subWeek()->toDateString(), 'department' => $dep]) }}">&larr; Last week</a>
    <a class="btn" href="{{ route('hr.roster', ['week' => $week->copy()->addWeek()->toDateString(), 'department' => $dep]) }}">Next week &rarr;</a></form>
<p class="muted small" style="margin:8px 0 0">Leave a box on <em>Usual</em> to follow the person's normal shift, weekly off and the holidays. Pick a shift or <em>Day off</em> only where this week is different.</p></div></div>
<form method="POST" action="{{ route('hr.roster.save') }}">@csrf<input type="hidden" name="week" value="{{ $week->toDateString() }}"><input type="hidden" name="department" value="{{ $dep }}">
<div class="panel">@if ($people->isEmpty())<div class="empty-state">No one to roster.</div>@else
<div class="table-wrap"><table class="roster"><thead><tr><th>Employee</th>@foreach ($days as $d)<th>{{ $d->format('D d') }}</th>@endforeach</tr></thead><tbody>
@foreach ($people as $p)
<tr><td><strong>{{ $p->code }}</strong> {{ $p->full_name }}<div class="muted small">{{ $p->shift?->name ?? 'No usual shift' }}</div></td>
@foreach ($days as $d)
@php $k = $d->toDateString(); $en = $entries->get($p->id)?->get($k); $val = $en ? ($en->shift_id ?? 'OFF') : ''; $pl = $defaults[$k][$p->id] ?? null; @endphp
<td><select name="cells[{{ $p->id }}][{{ $k }}]"><option value="">Usual</option><option value="OFF" @selected($val === 'OFF')>Day off</option>@foreach ($shifts as $s)<option value="{{ $s->id }}" @selected($val === $s->id)>{{ $s->name }}</option>@endforeach</select>
<div class="dflt">{{ $en ? 'changed' : ($pl ? ['WORK' => 'works', 'OFF' => 'weekly off', 'HOLIDAY' => 'holiday', 'LEAVE' => 'on leave'][$pl['kind']] : '') }}</div></td>
@endforeach</tr>
@endforeach</tbody></table></div>
<div class="panel-body"><button class="btn btn-primary">Save the roster</button></div>@endif</div></form>

<div class="tab-marker" data-tab-start="Shifts"></div>
<div class="panel"><div class="table-wrap"><table><thead><tr><th>Code</th><th>Shift</th><th>Hours</th><th>Break</th><th>Grace</th><th></th></tr></thead><tbody>
@foreach ($shifts as $s)
<tr><td><strong>{{ $s->code }}</strong></td><td>{{ $s->name }}@if ($s->crossesMidnight()) <span class="status status-in-progress">Night</span>@endif</td><td>{{ substr($s->starts_at, 0, 5) }} – {{ substr($s->ends_at, 0, 5) }} ({{ \App\Domains\Hr\Models\AttendanceRecord::hm($s->paidMinutes()) }} paid)</td><td>{{ $s->break_minutes }} min</td><td>{{ $s->grace_minutes }} min</td>
<td><details class="row-edit">@include('partials.icon-button', ['type' => 'edit', 'name' => $s->name, 'tag' => 'summary'])<form method="POST" action="{{ route('hr.shifts.update', $s) }}" class="form-grid">@csrf @method('PUT')
@include('hr::_in', ['name' => 'code', 'label' => 'Code', 'val' => $s->code]) @include('hr::_in', ['name' => 'name', 'label' => 'Name', 'val' => $s->name, 'required' => true])
@include('hr::_in', ['name' => 'starts_at', 'label' => 'Starts', 'type' => 'time', 'val' => substr($s->starts_at, 0, 5), 'required' => true]) @include('hr::_in', ['name' => 'ends_at', 'label' => 'Ends', 'type' => 'time', 'val' => substr($s->ends_at, 0, 5), 'required' => true])
@include('hr::_in', ['name' => 'break_minutes', 'label' => 'Break (min)', 'type' => 'number', 'val' => $s->break_minutes, 'required' => true]) @include('hr::_in', ['name' => 'grace_minutes', 'label' => 'Grace (min)', 'type' => 'number', 'val' => $s->grace_minutes, 'required' => true])
<div class="field"><label><input type="checkbox" name="active" value="1" @checked($s->active)> In use</label></div><div class="field"><button class="btn btn-primary">Save</button></div></form></details></td></tr>
@endforeach</tbody></table></div>
<div class="panel-body"><form method="POST" action="{{ route('hr.shifts.store') }}">@csrf<div class="form-grid">
@include('hr::_in', ['name' => 'name', 'label' => 'New shift', 'val' => '', 'required' => true, 'ph' => 'e.g. Pouring shift'])
@include('hr::_in', ['name' => 'starts_at', 'label' => 'Starts', 'type' => 'time', 'val' => '08:00', 'required' => true]) @include('hr::_in', ['name' => 'ends_at', 'label' => 'Ends', 'type' => 'time', 'val' => '17:00', 'required' => true, 'help' => 'An end earlier than the start means the shift runs past midnight.'])
@include('hr::_in', ['name' => 'break_minutes', 'label' => 'Break (min)', 'type' => 'number', 'val' => 60, 'required' => true]) @include('hr::_in', ['name' => 'grace_minutes', 'label' => 'Grace for late arrival (min)', 'type' => 'number', 'val' => 10, 'required' => true])
</div><p><button class="btn btn-primary">Add shift</button></p></form></div></div>

<div class="tab-marker" data-tab-start="Holidays"></div>
<div class="panel"><div class="panel-header"><h2>Holidays {{ $year }}</h2>
<form method="GET" style="display:flex;gap:6px"><input type="hidden" name="tab" value="holidays"><input type="number" name="year" value="{{ $year }}" style="width:90px;min-height:28px;padding:2px 6px"><button class="btn btn-small">Show</button></form></div>
<div class="table-wrap"><table><thead><tr><th>Date</th><th>Holiday</th><th>Kind</th><th></th></tr></thead><tbody>
@forelse ($holidays as $h)<tr><td>{{ $h->holiday_on->format('D d M Y') }}</td><td>{{ $h->name }}</td><td>{{ ucfirst(strtolower($h->kind)) }}</td><td><form method="POST" action="{{ route('hr.holidays.destroy', $h) }}" onsubmit="return confirm('Remove this holiday?')">@csrf @method('DELETE')@include('partials.icon-button', ['type' => 'delete', 'word' => 'Remove', 'name' => $h->name])</form></td></tr>
@empty<tr><td colspan="4" class="muted">None for {{ $year }}.</td></tr>@endforelse</tbody></table></div>
<div class="panel-body"><form method="POST" action="{{ route('hr.holidays.store') }}">@csrf<div class="form-grid">
@include('hr::_in', ['name' => 'holiday_on', 'label' => 'Date', 'type' => 'date', 'val' => '', 'required' => true]) @include('hr::_in', ['name' => 'name', 'label' => 'Holiday', 'val' => '', 'required' => true, 'ph' => 'e.g. Eid ul Fitr'])
@include('hr::_sel', ['name' => 'kind', 'label' => 'Kind', 'items' => ['PUBLIC' => 'Public holiday', 'COMPANY' => 'Company holiday', 'OPTIONAL' => 'Optional'], 'sel' => 'PUBLIC', 'blank' => false])
</div><p><button class="btn btn-primary">Add holiday</button></p></form>
<form method="POST" action="{{ route('hr.holidays.fixed') }}" style="margin-top:6px">@csrf<input type="hidden" name="year" value="{{ $year }}"><button class="btn">Add the fixed-date national holidays for {{ $year }}</button> <span class="muted small">Check them against the government notification; Eid and other moon-dependent days are added by hand.</span></form></div></div>
@if ($tab)<script>document.addEventListener('DOMContentLoaded', function () { var m = {roster:'Roster',shifts:'Shifts',holidays:'Holidays'}[@json($tab)]; var b = [].slice.call(document.querySelectorAll('.tabbar button')).filter(function (x) { return x.textContent.trim() === m; })[0]; if (b) b.click(); });</script>@endif
@endsection
