@extends('m.layout')
@section('title', 'Thermal analysis')
@section('body')
<style>
.stg{display:grid;grid-template-columns:repeat(3,1fr);gap:4px;padding:3px;border-radius:10px;background:#f0f1f3}
.stg label{cursor:pointer}.stg input,.cup input{position:absolute;opacity:0;pointer-events:none}
.stg span{display:block;height:40px;line-height:40px;text-align:center;border-radius:8px;background:transparent;color:#55585d;font-size:14px;font-weight:500}
.stg input:checked+span{background:#fff;color:#1c1d1f}
.cup span{display:flex;flex-direction:column;gap:2px;align-items:flex-start;padding:12px 14px;border-radius:12px;border:1px solid #e6e7e9;background:#fff;text-align:left;cursor:pointer}
.cup input:checked+span{border:2px solid #2f5fd0;background:#f5f8fe;padding:11px 13px}
.cup b{font-size:15px;font-weight:600}.cup small{font-size:13px;color:#55585d}
.rd{display:block;border:1px solid #e6e7e9;border-radius:12px;padding:10px 12px;background:#fff}
.rd[hidden]{display:none}.rd div{display:flex;justify-content:space-between;font-size:12.5px;color:#55585d}.rd div b{font-weight:600}
.rd input{width:100%;border:0;outline:0;padding:0;margin:0;font:600 22px system-ui;color:#1c1d1f;background:transparent}
.rd:focus-within{border-color:#2f5fd0}.rd input::placeholder{font-size:16px;font-weight:500;color:#6b6e73}
</style>
<div style="padding:10px 20px 8px;display:flex;flex-direction:column;gap:2px">
    <div style="font-size:12.5px;color:#55585d">Thermal analysis · {{ $heat->furnace?->name ?? $heat->furnace?->code }}</div>
    <div style="font-size:22px;font-weight:600">Set up the test</div>
    <div style="font-size:13px;color:#55585d"><code>{{ $heat->heat_number }}</code>@if($heat->ironGrade?->code) · {{ $heat->ironGrade->code }}@endif · {{ $heat->thermal_tests_count ? 're-test '.($heat->thermal_tests_count + 1) : 'first test' }}</div>
</div>
@if(session('notice'))<div style="margin:2px 20px 4px;padding:10px 14px;border-radius:12px;background:#fdf6e8;color:#94610a;font-size:14px">{{ session('notice') }}</div>@endif
<form method="POST" action="{{ route('m.tests.thermal.store', $heat) }}" data-offline data-offline-label="Thermal · {{ $heat->heat_number }}" data-offline-done="{{ route('m.home') }}" style="display:flex;flex-direction:column;flex:1">@csrf
<div style="padding:4px 20px 0;display:flex;flex-direction:column;gap:8px">
    <div class="cap">Stage</div>
    <div class="stg">@foreach($stages as $k => $l)<label><input type="radio" name="stage" value="{{ $k }}" @checked(old('stage', $stage) === $k)><span>{{ $l }}</span></label>@endforeach</div>
</div>
<div style="padding:14px 20px 0;display:flex;flex-direction:column;gap:8px">
    <div class="cap">Cup</div>
    @foreach($cups as $k => [$l, $d])<label class="cup"><input type="radio" name="cup_type" value="{{ $k }}" @checked(old('cup_type', $cup) === $k)><span><b>{{ $l }}</b><small>{{ $d }}</small></span></label>@endforeach
    <div id="cup-note" style="font-size:13px;color:#55585d"></div>
</div>
<div style="padding:14px 20px 0;display:grid;grid-template-columns:1fr 1fr;gap:8px">
    <label class="rd" data-cups="STABLE TELLURIUM"><div><b>TL</b><span>°C</span></div><input type="number" step="0.1" inputmode="decimal" name="tl_c" value="{{ old('tl_c') }}" placeholder="Liquidus" required></label>
    <label class="rd" data-cups="STABLE TELLURIUM"><div><b>Peak temp</b><span>°C</span></div><input type="number" step="0.1" inputmode="decimal" name="peak_temp_c" value="{{ old('peak_temp_c') }}" placeholder="Highest" required></label>
    <label class="rd" data-cups="TELLURIUM"><div><b>Solidus</b><span>°C</span></div><input type="number" step="0.1" inputmode="decimal" name="solidus_c" value="{{ old('solidus_c') }}" placeholder="TS" required></label>
    <label class="rd" data-cups="STABLE"><div><b>TE min</b><span>°C</span></div><input type="number" step="0.1" inputmode="decimal" name="te_min_c" value="{{ old('te_min_c') }}" placeholder="Eutectic low" required></label>
    <label class="rd" data-cups="STABLE"><div><b>TE max</b><span>°C</span></div><input type="number" step="0.1" inputmode="decimal" name="te_max_c" value="{{ old('te_max_c') }}" placeholder="Eutectic high" required></label>
    <label class="rd" data-cups="TELLURIUM"><div><b>Carbon</b><span>%</span></div><input type="number" step="0.0001" inputmode="decimal" name="carbon" value="{{ old('carbon') }}" placeholder="C" required></label>
    <label class="rd" data-cups="TELLURIUM"><div><b>Silicon</b><span>%</span></div><input type="number" step="0.0001" inputmode="decimal" name="silicon" value="{{ old('silicon') }}" placeholder="Si" required></label>
    <label class="rd" data-cups="TELLURIUM"><div><b>CE</b><span></span></div><input type="number" step="0.0001" inputmode="decimal" name="ce" value="{{ old('ce') }}" placeholder="Carbon equivalent" required></label>
</div>
<div style="padding:14px 20px 0;display:flex;flex-direction:column">
    <label style="display:flex;justify-content:space-between;align-items:center;gap:12px;padding:12px 0;border-top:1px solid #eff0f2;border-bottom:1px solid #eff0f2;font-size:15px"><span style="color:#55585d">Cup batch</span><input type="text" name="cup_batch" value="{{ old('cup_batch') }}" autocomplete="off" placeholder="e.g. QC-4471" style="flex:1;min-width:0;border:0;outline:0;text-align:right;font:inherit;background:transparent"></label>
    @if($errors->any() && ! $errors->has('reason') && ! $errors->has('note'))<div class="err" style="margin-top:10px">{{ $errors->first() }}</div>@endif
</div>
<div style="flex:1"></div>
<div class="stick" style="border-top:0;margin-bottom:0"><button class="btn" style="height:52px;border-radius:12px">Save to heat</button></div>
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
        document.getElementById('cup-note').textContent = cup === 'TELLURIUM' ? 'Default for grey iron. Change only if the melt lead asks.' : 'Plain cup on grey iron gives graphite and shrink only, no chemistry.';
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
