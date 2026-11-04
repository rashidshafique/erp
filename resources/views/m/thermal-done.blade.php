@extends('m.layout')
@section('title', 'Thermal result')
@section('body')
@php $ok = $test->result === 'PASS'; $bad = $test->result === 'FAIL'; @endphp
<div style="padding:24px 20px 8px">
    <div class="cap">Thermal analysis · <code>{{ $test->heat?->heat_number }}</code></div>
    <div style="font-size:34px;font-weight:700;margin-top:6px" class="{{ $ok ? 'pass' : ($bad ? 'fail' : '') }}">{{ str_replace('_', ' ', $test->result) }}</div>
    @foreach(collect($test->findings ?? [])->flatten() as $f)<div class="muted" style="font-size:15px;margin-top:6px">{{ $f }}</div>@endforeach
    @if(session('warning'))<div class="fail" style="font-size:14px;margin-top:10px">{{ session('warning') }}</div>@endif
</div>
<div style="padding:16px 20px;display:flex;flex-direction:column;gap:10px;margin-top:auto">
    <a class="tile" href="{{ route('m.tests.thermal', $test->heat_id) }}"><b>Another cup on this heat</b><span>Re-test or the other cup</span></a>
    <a class="btn" href="{{ route('m.home') }}" style="text-align:center;line-height:52px;text-decoration:none">Done</a>
</div>
@endsection
