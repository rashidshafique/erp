{{-- Quality home: what waits on the inspectors, what failed in the last day, what is open. No design exists for this page; it follows the owner's home (4a) frame. --}}
@extends('layouts.app')
@section('title', 'Quality')
@section('breadcrumb', 'Quality')
@section('content')
@php $first = strtok(auth()->user()?->name ?? '', ' '); @endphp
<style>
.qh { display: flex; flex-direction: column; gap: 20px; }
.qh h1 { margin: 0; font-size: 22px; font-weight: 600; letter-spacing: -.01em; }
.qh-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; }
.qh-kpi { display: block; border: 1px solid var(--border); border-radius: 8px; padding: 14px 16px; background: var(--surface); color: inherit; text-decoration: none; }
.qh-kpi .l { font-size: 12px; color: var(--muted); } .qh-kpi .v { margin-top: 4px; font-size: 24px; font-weight: 600; }
.qh-kpi.bad .v { color: #b3352c; } .qh-kpi.warn .v { color: #94610a; }
.qh-cols { display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 16px; }
.qh-card { border: 1px solid var(--border); border-radius: 8px; background: var(--surface); }
.qh-card h2 { margin: 0; padding: 12px 16px; font-size: 14px; font-weight: 600; border-bottom: 1px solid var(--border); }
.qh-row { display: flex; justify-content: space-between; gap: 12px; padding: 10px 16px; border-top: 1px solid var(--border); color: inherit; text-decoration: none; font-size: 13.5px; }
.qh-row:first-of-type { border-top: 0; } .qh-row span:last-child { color: var(--muted); white-space: nowrap; }
.qh-none { padding: 14px 16px; color: var(--muted); font-size: 13.5px; }
</style>
<div class="qh">
    <div><h1>Quality{{ $first ? ', '.$first : '' }}</h1><div class="muted small">{{ now()->format('l j F') }}</div></div>
    <div class="qh-kpis">
        <a class="qh-kpi {{ $awaitingCount ? 'warn' : '' }}" href="{{ route('inspections.index') }}"><div class="l">Lots awaiting inspection</div><div class="v">{{ $awaitingCount }}</div></a>
        <a class="qh-kpi" href="{{ route('inspections.index', ['status' => 'IN_PROGRESS']) }}"><div class="l">Inspections in progress</div><div class="v">{{ $inProgress }}</div></a>
        <a class="qh-kpi {{ $ncrCount ? 'bad' : '' }}" href="{{ route('ncrs.index') }}"><div class="l">Open nonconformances</div><div class="v">{{ $ncrCount }}</div></a>
        <div class="qh-kpi {{ $onHold ? 'warn' : '' }}"><div class="l">Lots on hold</div><div class="v">{{ $onHold }}</div></div>
        <div class="qh-kpi {{ $gaugesDue ? 'warn' : '' }}"><div class="l">Gauges due for calibration</div><div class="v">{{ $gaugesDue }}</div></div>
        <div class="qh-kpi"><div class="l">Tests recorded today</div><div class="v">{{ $testsToday }}</div></div>
    </div>
    <div class="qh-cols">
        <div class="qh-card"><h2>Waiting for inspection</h2>
            @forelse($awaiting as $lot)<a class="qh-row" href="{{ route('lots.inspections.create', $lot) }}"><span><b>{{ $lot->lot_number }}</b> · {{ $lot->product?->code }}</span><span>{{ $lot->quantity_good }} pcs · {{ $lot->created_at->diffForHumans(null, true) }}</span></a>@empty<div class="qh-none">Nothing waiting.</div>@endforelse
        </div>
        <div class="qh-card"><h2>Failed in the last 24 hours</h2>
            @foreach($failedThermal as $t)<a class="qh-row" href="{{ route('thermal.show', $t) }}"><span>Thermal · <b>{{ $t->heat?->heat_number }}</b></span><span>{{ $t->taken_at?->diffForHumans() }}</span></a>@endforeach
            @foreach($failedInspections as $i)<a class="qh-row" href="{{ route('inspections.show', $i) }}"><span>{{ ucfirst(strtolower($i->inspection_type)) }} · <b>{{ $i->lot?->lot_number }}</b></span><span>{{ $i->quantity_rejected }} rejected · {{ $i->quantity_rework }} rework</span></a>@endforeach
            @if($failedThermal->isEmpty() && $failedInspections->isEmpty())<div class="qh-none">No failures.</div>@endif
        </div>
        <div class="qh-card"><h2>Open nonconformances</h2>
            @forelse($ncrs as $n)<a class="qh-row" href="{{ route('ncrs.show', $n) }}"><span><b>{{ $n->ncr_number }}</b> · {{ \Illuminate\Support\Str::limit($n->title, 40) }}</span><span>{{ strtolower($n->severity) }}</span></a>@empty<div class="qh-none">None open.</div>@endforelse
        </div>
    </div>
</div>
@endsection
