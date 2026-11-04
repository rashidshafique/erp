@extends('m.layout')
@section('title', 'Spectro')
@section('body')
<div style="padding:14px 20px 6px"><a href="{{ route('m.tests.pick', 'spectro') }}" class="blue" style="font-size:14px;text-decoration:none">‹ Heats</a>
    <div style="font-size:22px;font-weight:600;margin-top:4px">Spectro</div>
    <div class="muted" style="font-size:13px"><code>{{ $heat->heat_number }}</code> · {{ $heat->furnace?->code ?? $heat->furnace?->name }} · {{ $heat->ironGrade?->code ?? 'no grade set' }}</div></div>
<form method="POST" action="{{ route('m.tests.spectro.store', $heat) }}" data-offline data-offline-label="Spectro · {{ $heat->heat_number }}" data-offline-done="{{ route('m.home') }}" style="display:flex;flex-direction:column;flex:1">@csrf
<div style="padding:8px 20px;display:flex;flex-direction:column;gap:14px;flex:1">
    <div><span class="cap">Sample</span><div class="seg" style="margin-top:6px">@foreach($stages as $k => $l)<label><input type="radio" name="sample_stage" value="{{ $k }}" @checked(old('sample_stage', 'AFTER_TREATMENT') === $k)><span>{{ $l }}</span></label>@endforeach</div></div>
    @foreach($elements as $key => $label)
        <label class="field"><span>{{ $label }} %</span><input type="number" step="0.001" inputmode="decimal" name="{{ $columns[$key] }}" value="{{ old($columns[$key]) }}"></label>
    @endforeach
    <label class="field"><span>Sample reference</span><input type="text" name="sample_reference" value="{{ old('sample_reference') }}" autocomplete="off"></label>
    @if($errors->any())<div class="err">{{ $errors->first() }}</div>@endif
</div>
<div class="stick"><button class="btn">Save analysis</button></div>
</form>
@endsection
