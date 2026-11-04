@extends('m.layout')
@section('title', 'Lot defects')
@section('body')
<div style="padding:14px 20px 6px"><a href="{{ route('m.home') }}" class="blue" style="font-size:14px;text-decoration:none">‹ Today</a>
    <div class="muted" style="font-size:13px;margin-top:4px"><code>{{ $lot->lot_number }}</code> · {{ $lot->product?->code }} · {{ $lot->quantity_poured }} pcs</div>
    @if($latest)
        <div style="font-size:22px;font-weight:600;margin-top:6px">Defect logged</div>
        <div style="font-size:15px;margin-top:4px">{{ $latest->quantity }} × {{ $latest->defect?->name }}{{ $latest->location ? ', '.$latest->location : '' }}, {{ strtolower(\App\Models\QualityDefectMark::DISPOSITIONS[$latest->disposition]) }}.</div>
        <div class="muted" style="font-size:14px;margin-top:4px">Lot count now {{ $lot->quantity_good }} good of {{ $lot->quantity_poured }}.</div>
        @if($repeats > $latest->quantity)<div class="fail" style="font-size:14px;margin-top:6px">{{ $repeats }} with this defect{{ $latest->location ? ' at '.$latest->location : '' }} today on this part.</div>@endif
    @else
        <div style="font-size:22px;font-weight:600;margin-top:6px">{{ $lot->quantity_good }} good of {{ $lot->quantity_poured }}</div>
    @endif
</div>
<div style="padding:12px 20px 0"><div class="cap" style="padding-bottom:2px">On this lot today</div>
    @forelse($today as $m)<div class="row"><span><b>{{ $m->defect?->name }}{{ $m->location ? ' · '.$m->location : '' }}</b><br><span class="muted" style="font-size:13px">{{ $m->quantity }} pc{{ $m->quantity === 1 ? '' : 's' }} · {{ strtolower(\App\Models\QualityDefectMark::DISPOSITIONS[$m->disposition]) }} · {{ $m->created_at->format('H:i') }}</span></span></div>@empty<div class="muted" style="font-size:14px;padding:10px 0">Nothing logged today.</div>@endforelse
</div>
<div style="padding:16px 20px;display:flex;flex-direction:column;gap:10px;margin-top:auto">
    <a class="tile" href="{{ route('m.defects.log', $lot) }}"><b>Log another on this lot</b></a>
    <a class="tile" href="{{ route('m.scan') }}"><b>Scan next lot</b></a>
</div>
@endsection
