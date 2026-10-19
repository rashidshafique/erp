@extends('layouts.app')
@section('title', 'Payroll')
@section('breadcrumb', 'Payroll')
@section('content')
@php $cls = ['DRAFT' => 'status-on-hold', 'CALCULATED' => 'status-in-progress', 'APPROVED' => 'status-released', 'PAID' => 'status-completed']; $m = fn ($v) => \App\Support\Formats::number((float) $v); @endphp
<div class="page-heading"><div><h1>Payroll</h1><p>One payroll a month: calculate from attendance and leave, check, approve, then pay.</p></div>
<div class="actions"><a class="btn" href="{{ route('hr.payroll.advances') }}">Advances &amp; loans</a><a class="btn" href="{{ route('hr.payroll.settlements') }}">Final settlements</a><a class="btn" href="{{ route('hr.payroll.statutory') }}">Statutory reports</a><a class="btn" href="{{ route('hr.payroll.setup') }}">Payroll setup</a></div></div>
@if (session('error'))<div class="alert alert-error">{{ session('error') }}</div>@endif
@include('hr::payroll._tabs_note')
@include('hr::_ui')
@php
    $by = $periods->groupBy('status'); $last = $periods->first(); $ls = $last?->summary ?? []; $prevP = $periods->skip(1)->first(); $ps = $prevP?->summary ?? [];
    $delta = isset($ls['net'], $ps['net']) && $ps['net'] > 0 ? round(100 * ($ls['net'] - $ps['net']) / $ps['net'], 1) : null;
    $strip = [['Draft', $by->get('DRAFT', collect())->count(), 'file-text'], ['Calculated', $by->get('CALCULATED', collect())->count(), 'list-check'], ['Approved', $by->get('APPROVED', collect())->count(), 'check-circle'], ['Paid', $by->get('PAID', collect())->count(), 'wallet']];
@endphp
<div class="hr-cards">
@foreach ($strip as [$l, $n, $ic])
<div class="hr-card"><span class="hr-ico">{!! \App\Support\NavIcons::get($ic, 18) !!}</span><div><div class="hr-l">{{ $l }}</div><div class="hr-n">{{ str_pad((string) $n, 2, '0', STR_PAD_LEFT) }}</div></div></div>
@endforeach
<div class="hr-card"><span class="hr-ico">{!! \App\Support\NavIcons::get('receipt', 18) !!}</span><div><div class="hr-l">{{ $last ? $last->month->format('M Y').' net pay' : 'Latest net pay' }}</div><div class="hr-n">{{ isset($ls['net']) ? $m($ls['net']) : '—' }}@if ($delta !== null)<span class="hr-d {{ $delta == 0 ? 'flat' : ($delta > 0 ? 'up' : 'down') }}">{{ $delta > 0 ? '+' : '' }}{{ $delta }}% vs prev</span>@endif</div></div></div>
</div>
<div class="panel"><div class="panel-body"><form method="POST" action="{{ route('hr.payroll.store') }}" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">@csrf
<div class="field" style="margin:0"><label for="month">Open a payroll for</label><input id="month" type="month" name="month" value="{{ $next->format('Y-m') }}" required style="width:170px"></div><button class="btn btn-primary">Open payroll</button></form></div></div>
<div class="panel">@if ($periods->isNotEmpty())<div class="panel-body" style="padding-bottom:0"><input id="pr-q" type="search" placeholder="Search month or status…" style="max-width:320px" aria-label="Search payrolls" autocomplete="off"></div>@endif @if ($periods->isEmpty())<div class="empty-state">No payroll has been opened yet.</div>@else
<div class="table-wrap"><table><thead><tr><th>Month</th><th>People</th><th>Gross</th><th>Deductions</th><th>Net pay</th><th>Status</th></tr></thead><tbody>
@foreach ($periods as $p)@php $s = $p->summary ?? []; @endphp
<tr data-q="{{ mb_strtolower($p->month->format('F Y').' '.$p->code.' '.$p->status) }}"><td><a href="{{ route('hr.payroll.show', $p) }}"><strong>{{ $p->month->format('F Y') }}</strong></a><div class="muted small">{{ $p->code }}</div></td>
<td>{{ $s['people'] ?? '—' }}</td><td>{{ isset($s['gross']) ? $m($s['gross']) : '—' }}</td><td>{{ isset($s['gross']) ? $m($s['gross'] - $s['net']) : '—' }}</td><td><strong>{{ isset($s['net']) ? $m($s['net']) : '—' }}</strong></td>
<td><span class="status {{ $cls[$p->status] }}">{{ ucfirst(strtolower($p->status)) }}</span></td></tr>
@endforeach</tbody></table></div>@endif</div>
<script>(function(){var q=document.getElementById('pr-q');if(!q)return;q.addEventListener('input',function(){var t=q.value.trim().toLowerCase();document.querySelectorAll('tr[data-q]').forEach(function(r){r.style.display=t===''||r.dataset.q.indexOf(t)>=0?'':'none';});});})();</script>
@endsection
