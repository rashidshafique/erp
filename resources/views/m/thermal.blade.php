@extends('m.layout')
@section('title', 'Thermal analysis')
@section('body')
<div style="padding:14px 20px 6px"><a href="{{ route('m.tests.pick', 'thermal') }}" class="blue" style="font-size:14px;text-decoration:none">‹ Heats</a>
    <div style="font-size:22px;font-weight:600;margin-top:4px">Thermal analysis</div>
    <div class="muted" style="font-size:13px"><code>{{ $heat->heat_number }}</code> · {{ $heat->furnace?->code ?? $heat->furnace?->name }} · {{ $heat->ironGrade?->code ?? 'no grade set' }}</div></div>
@if(session('notice'))<div style="margin:6px 20px 0;padding:10px 14px;border-radius:12px;background:#fdf6e8;color:#94610a;font-size:14px">{{ session('notice') }}</div>@endif
<form method="POST" action="{{ route('m.tests.thermal.store', $heat) }}" data-offline data-offline-label="Thermal · {{ $heat->heat_number }}" data-offline-done="{{ route('m.home') }}" style="display:flex;flex-direction:column;flex:1">@csrf
<div style="padding:8px 20px;display:flex;flex-direction:column;gap:16px;flex:1">
    <div><span class="cap">Stage</span><div class="seg" style="margin-top:6px">@foreach($stages as $k => $l)<label><input type="radio" name="stage" value="{{ $k }}" @checked(old('stage', $stage) === $k)><span>{{ $l }}</span></label>@endforeach</div></div>
    <div><span class="cap">Cup</span><div class="seg" style="margin-top:6px">@foreach($cups as $k => [$l, $d])<label><input type="radio" name="cup_type" value="{{ $k }}" @checked(old('cup_type', $cup) === $k)><span>{{ $l }}<small>{{ $d }}</small></span></label>@endforeach</div></div>
    <label class="field" data-cups="STABLE TELLURIUM"><span>Liquidus TL, °C</span><input type="number" step="0.1" inputmode="decimal" name="tl_c" value="{{ old('tl_c') }}" required></label>
    <label class="field" data-cups="STABLE"><span>Eutectic minimum TE min, °C</span><input type="number" step="0.1" inputmode="decimal" name="te_min_c" value="{{ old('te_min_c') }}"></label>
    <label class="field" data-cups="STABLE"><span>Eutectic maximum TE max, °C</span><input type="number" step="0.1" inputmode="decimal" name="te_max_c" value="{{ old('te_max_c') }}"></label>
    <label class="field" data-cups="TELLURIUM"><span>Solidus, °C</span><input type="number" step="0.1" inputmode="decimal" name="solidus_c" value="{{ old('solidus_c') }}"></label>
    <label class="field"><span>Cup batch</span><input type="text" name="cup_batch" value="{{ old('cup_batch') }}" autocomplete="off"></label>
    @if($errors->any() && ! $errors->has('reason') && ! $errors->has('note'))<div class="err">{{ $errors->first() }}</div>@endif
</div>
<div class="stick"><button class="btn">Save test</button></div>
</form>
<form id="unfinished" method="POST" action="{{ route('m.tests.thermal.unfinished', $heat) }}" data-offline data-offline-label="Invalid thermal · {{ $heat->heat_number }}" data-offline-done="{{ route('m.tests.thermal', $heat) }}" style="padding:0 20px 16px">@csrf
    <input type="hidden" name="stage"><input type="hidden" name="cup_type"><input type="hidden" name="cup_batch">
    <details @if($errors->has('reason') || $errors->has('note')) open @endif style="border:1px solid #d9dbde;border-radius:14px;padding:12px 14px">
        <summary style="font-size:15px;font-weight:600;cursor:pointer">The test did not finish</summary>
        <div class="muted" style="font-size:13px;margin:8px 0">Log it as invalid, with the cup and stage above, then take it again.</div>
        <div class="seg" style="flex-direction:column">@foreach($unfinished as $k => $l)<label><input type="radio" name="reason" value="{{ $k }}" @checked(old('reason') === $k)><span>{{ $l }}</span></label>@endforeach</div>
        <label class="field" style="margin-top:10px"><span>Note (needed for Something else)</span><textarea name="note" maxlength="300">{{ old('note') }}</textarea></label>
        @if($errors->has('reason') || $errors->has('note'))<div class="err" style="margin-top:6px">{{ $errors->first() }}</div>@endif
        <button class="btn" style="margin-top:12px;background:#b3352c">Log invalid test and retake</button>
    </details>
</form>
<script>
(function () {
    var f = document.querySelector('form[data-offline]');
    function sync() {
        var cup = f.querySelector('input[name=cup_type]:checked').value;
        f.querySelectorAll('[data-cups]').forEach(function (l) {
            var on = l.dataset.cups.split(' ').indexOf(cup) > -1, i = l.querySelector('input');
            l.hidden = !on; i.disabled = !on; i.required = on;
        });
    }
    var u = document.getElementById('unfinished');
    function copy() {
        ['stage', 'cup_type'].forEach(function (n) { u.querySelector('[name=' + n + ']').value = f.querySelector('input[name=' + n + ']:checked').value; });
        u.querySelector('[name=cup_batch]').value = f.querySelector('[name=cup_batch]').value;
    }
    f.addEventListener('change', function () { sync(); copy(); });
    f.addEventListener('input', copy);
    sync(); copy();
})();
</script>
@endsection
