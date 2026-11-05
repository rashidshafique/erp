@extends('m.layout')
@section('title', 'Thermal result')
@section('body')
@php $void = $test->isVoided(); $ok = ! $void && $test->result === 'PASS'; $bad = ! $void && $test->result === 'FAIL'; @endphp
<div style="padding:24px 20px 8px">
    <div class="cap">Thermal analysis · <code>{{ $test->heat?->heat_number }}</code></div>
    @if($void)
        <div style="font-size:34px;font-weight:700;margin-top:6px;color:#94610a">INVALID</div>
        <div class="muted" style="font-size:15px;margin-top:6px">{{ $test->void_reason }}</div>
    @else
        <div style="font-size:34px;font-weight:700;margin-top:6px" class="{{ $ok ? 'pass' : ($bad ? 'fail' : '') }}">{{ str_replace('_', ' ', $test->result) }}</div>
        @foreach(collect($test->findings ?? [])->flatten() as $f)<div class="muted" style="font-size:15px;margin-top:6px">{{ $f }}</div>@endforeach
    @endif
    @if(session('warning'))<div class="fail" style="font-size:14px;margin-top:10px">{{ session('warning') }}</div>@endif
</div>
@if(! $void)
<form method="POST" action="{{ route('m.tests.thermal.invalid', $test) }}" style="padding:8px 20px 0">@csrf
    <details @if($errors->any()) open @endif style="border:1px solid #d9dbde;border-radius:14px;padding:12px 14px">
        <summary style="font-size:15px;font-weight:600;cursor:pointer">Something wrong with this test?</summary>
        <div class="muted" style="font-size:13px;margin:8px 0">Mark it invalid and it stops counting for the pour gate and certificates. You then take it again.</div>
        <div class="seg" style="flex-direction:column">@foreach($reasons as $k => $l)<label><input type="radio" name="reason" value="{{ $k }}" @checked(old('reason') === $k)><span>{{ $l }}</span></label>@endforeach</div>
        <label class="field" style="margin-top:10px"><span>Note (needed for Something else)</span><textarea name="note" maxlength="300">{{ old('note') }}</textarea></label>
        @if($errors->any())<div class="err" style="margin-top:6px">{{ $errors->first() }}</div>@endif
        <button class="btn" style="margin-top:12px;background:#b3352c">Mark invalid and retake</button>
    </details>
</form>
@endif
<div style="padding:16px 20px;display:flex;flex-direction:column;gap:10px;margin-top:auto">
    @if($void)
        <a class="btn" href="{{ route('m.tests.thermal', ['heat' => $test->heat_id, 'stage' => $test->stage, 'cup' => $test->cup_type]) }}" style="text-align:center;line-height:52px;text-decoration:none">Take it again</a>
        <a class="tile" href="{{ route('m.home') }}"><b>Done</b><span>Back to today</span></a>
    @else
        <a class="tile" href="{{ route('m.tests.thermal', $test->heat_id) }}"><b>Another cup on this heat</b><span>Re-test or the other cup</span></a>
        <a class="btn" href="{{ route('m.home') }}" style="text-align:center;line-height:52px;text-decoration:none">Done</a>
    @endif
</div>
@endsection
