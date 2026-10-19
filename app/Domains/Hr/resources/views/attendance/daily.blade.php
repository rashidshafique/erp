@extends('layouts.app')
@section('title', 'Attendance')
@section('breadcrumb', 'Attendance')
@section('content')
@php
    $st = config('hr.attendance.day_status');
    $stCls = ['PRESENT' => 'status-completed', 'HALF_DAY' => 'status-on-hold', 'ABSENT' => 'status-cancelled', 'LEAVE' => 'status-in-progress', 'OFF' => '', 'HOLIDAY' => ''];
    $dep = request('department');
@endphp
<div class="page-heading"><div><h1>Attendance</h1><p>{{ $date->format('l, d F Y') }}</p></div>
<div class="actions"><a class="btn" href="{{ route('hr.attendance', ['date' => $date->copy()->subDay()->toDateString(), 'department' => $dep]) }}">&larr; Previous</a>
<a class="btn" href="{{ route('hr.attendance', ['department' => $dep]) }}">Today</a>
<a class="btn" href="{{ route('hr.attendance', ['date' => $date->copy()->addDay()->toDateString(), 'department' => $dep]) }}">Next &rarr;</a></div></div>
@include('hr::attendance._tabs')
@if (session('error'))<div class="alert alert-error">{{ session('error') }}</div>@endif
<div class="panel"><div class="panel-body"><form method="GET" class="filters">
    <div class="field"><label for="date">Day</label><input id="date" type="date" name="date" value="{{ $date->toDateString() }}"></div>
    <div class="field"><label for="department">Department</label><select id="department" name="department"><option value="">Everyone</option>@foreach ($departments as $x)<option value="{{ $x->id }}" @selected($dep === $x->id)>{{ $x->name }}</option>@endforeach</select></div>
    <button class="btn btn-primary">Show</button></form>
</div></div>
@include('hr::_ui')
<div class="hr-cards">@foreach ($cards as $c)
<div class="hr-card"><span class="hr-ico">{!! \App\Support\NavIcons::get(['present' => 'check-circle', 'late' => 'clock', 'absent' => 'alert', 'leave' => 'calendar-minus', 'overtime' => 'zap', 'open' => 'inbox'][$c['key']], 18) !!}</span>
<div><div class="hr-l">{{ $c['label'] }}</div><div class="hr-n">{{ $c['n'] }}@if ($c['delta'] !== null)<span class="hr-d {{ $c['delta'] == 0 ? 'flat' : (($c['delta'] > 0) === $c['good'] ? 'up' : 'down') }}">{{ $c['delta'] > 0 ? '+' : '' }}{{ $c['delta'] }}% vs prev day</span>@endif</div></div></div>
@endforeach</div>
@if ($missing > 0)<p class="small muted" style="margin:-4px 0 12px">{{ $missing }} with no record yet.</p>@endif

<form method="POST" action="{{ route('hr.attendance.save') }}">@csrf<input type="hidden" name="date" value="{{ $date->toDateString() }}"><input type="hidden" name="department" value="{{ $dep }}">
<div class="panel">
@if ($people->isEmpty())<div class="empty-state">No one to show for this day.</div>@else
<div class="table-wrap"><table><thead><tr><th>Employee</th><th>Plan</th><th style="width:110px">In</th><th style="width:110px">Out</th><th>Result</th><th>Worked</th><th>Note</th></tr></thead><tbody>
@foreach ($people as $p)
@php $r = $recs->get($p->id); $pl = $plans[$p->id]; @endphp
<tr>
<td><a href="{{ route('hr.employees.show', $p) }}"><strong>{{ $p->code }}</strong></a> <button type="button" class="hr-person" data-person-url="{{ route('hr.attendance.person', [$p, 'to' => $date->toDateString()]) }}">{{ $p->full_name }}</button><div class="muted small">{{ $p->department?->name }}</div></td>
<td class="small">@if ($pl['kind'] === 'WORK'){{ $pl['shift']?->label() ?? 'No shift set' }}@elseif ($pl['kind'] === 'HOLIDAY')Holiday: {{ $pl['holiday'] }}@elseif ($pl['kind'] === 'LEAVE')On leave: {{ $pl['leave']->type->name }}@else Weekly off @endif</td>
<td><input type="time" name="rows[{{ $p->id }}][in]" value="{{ $r?->check_in?->displayTime() }}" style="min-height:30px;padding:3px 6px"></td>
<td><input type="time" name="rows[{{ $p->id }}][out]" value="{{ $r?->check_out?->displayTime() }}" style="min-height:30px;padding:3px 6px"></td>
<td>@if ($r)<span class="status {{ $stCls[$r->status] ?? '' }}">{{ $st[$r->status] }}</span> @include('hr::_dayflags', ['flags' => $r->flags])@if ($r->late_minutes)<span class="muted small">{{ $r->late_minutes }} min late</span>@endif @else <span class="muted">—</span> @endif</td>
<td class="small">{{ $r ? \App\Domains\Hr\Models\AttendanceRecord::hm($r->worked_minutes) : '—' }}@if ($r && $r->overtime_minutes)<div class="muted">+{{ \App\Domains\Hr\Models\AttendanceRecord::hm($r->overtime_minutes) }} OT</div>@endif</td>
<td><input name="rows[{{ $p->id }}][note]" value="{{ $r?->note }}" style="min-height:30px;padding:3px 6px;max-width:200px"></td></tr>
@endforeach</tbody></table></div>
<div class="panel-body"><button class="btn btn-primary" type="submit">Save the sheet</button></div>
@endif
</div></form>
@if ($missing > 0 && $date->lte(now()))
<form method="POST" action="{{ route('hr.attendance.close') }}" onsubmit="return confirm('Give everyone without a record an absent, day-off, holiday or leave record for this day?')">@csrf<input type="hidden" name="date" value="{{ $date->toDateString() }}">
<p class="muted">When the day is over: <button class="btn" type="submit">Close the day ({{ $missing }} without a record)</button> &mdash; people who did not come in are marked absent and show up under Exceptions.</p></form>@endif
<div class="hr-veil" id="hr-veil"></div>
<aside class="hr-drawer" id="hr-drawer" aria-label="Person attendance" aria-hidden="true"><div class="hr-drawer-body" id="hr-body"></div></aside>
<script>
(function () {
    var veil = document.getElementById('hr-veil'), dr = document.getElementById('hr-drawer'), body = document.getElementById('hr-body');
    var btns = [].slice.call(document.querySelectorAll('[data-person-url]')), cur = -1, pane = 'a';
    function open(i, days) {
        if (i < 0 || i >= btns.length) return; cur = i;
        var u = new URL(btns[i].getAttribute('data-person-url'), location.href); if (days) u.searchParams.set('days', days);
        dr.classList.add('on'); veil.classList.add('on'); dr.setAttribute('aria-hidden', 'false');
        body.innerHTML = '<p class="muted" style="padding:24px">Loading…</p>';
        fetch(u, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' }).then(function (r) { if (!r.ok) throw 0; return r.text(); }).then(function (h) { body.innerHTML = h; wire(); }).catch(function () { body.innerHTML = '<p class="alert alert-error" style="margin:20px">Could not load this person.</p>'; });
    }
    function close() { dr.classList.remove('on'); veil.classList.remove('on'); dr.setAttribute('aria-hidden', 'true'); }
    function show(p) { pane = p; body.querySelectorAll('.hr-tabs button').forEach(function (b) { b.classList.toggle('on', b.dataset.pane === p); }); body.querySelectorAll('.hr-pane').forEach(function (x) { x.classList.toggle('on', x.dataset.p === p); }); }
    function wire() {
        show(pane);
        body.querySelectorAll('.hr-tabs button').forEach(function (b) { b.onclick = function () { show(b.dataset.pane); }; });
        body.querySelectorAll('[data-step]').forEach(function (b) { var n = cur + parseInt(b.dataset.step, 10); b.disabled = n < 0 || n >= btns.length; b.onclick = function () { open(n, body.querySelector('[data-days]') && body.querySelector('[data-days]').value); }; });
        var pos = body.querySelector('[data-pos]'); if (pos) pos.textContent = (cur + 1) + ' of ' + btns.length;
        var sel = body.querySelector('[data-days]'); if (sel) sel.onchange = function () { open(cur, sel.value); };
        body.querySelectorAll('[data-close-drawer]').forEach(function (b) { b.onclick = close; });
    }
    btns.forEach(function (b, i) { b.addEventListener('click', function () { open(i); }); });
    veil.addEventListener('click', close);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
})();
</script>
@endsection
