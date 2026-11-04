@extends('m.layout')
@section('title', 'Hardness')
@section('body')
@php $hasSpec = $spec['min'] !== null || $spec['max'] !== null; @endphp
<div style="padding:14px 20px 6px"><a href="{{ route('m.tests.pick', 'inspection') }}" class="blue" style="font-size:14px;text-decoration:none">‹ Lots</a>
    <div style="font-size:22px;font-weight:600;margin-top:4px">Hardness</div>
    <div class="muted" style="font-size:13px"><code>{{ $lot->lot_number }}</code> · {{ $lot->product?->code }}{{ $hasSpec ? ' · spec '.($spec['min'] ?? '').'–'.($spec['max'] ?? '').' '.$spec['unit'] : ' · no spec set for this part' }}</div></div>
<form method="POST" action="{{ route('m.tests.hardness.store', $lot) }}" data-offline data-offline-label="Hardness · {{ $lot->lot_number }}" data-offline-done="{{ route('m.home') }}" style="display:flex;flex-direction:column;flex:1">@csrf
<div style="padding:8px 20px;display:flex;flex-direction:column;gap:14px;flex:1">
    @for($i = 1; $i <= $count; $i++)
        <label class="field"><span>Reading {{ $i }}</span><input type="number" step="0.1" inputmode="decimal" name="readings[{{ $i }}]" value="{{ old('readings.'.$i) }}" class="rd" {{ $i === 1 ? 'autofocus' : '' }}></label>
    @endfor
    @if(! $hasSpec)<label class="field"><span>Unit</span><input type="text" name="unit" value="{{ old('unit', 'HBW') }}"></label>@endif
    <div id="out" class="fail" hidden style="font-size:15px"><label style="display:flex;gap:10px;align-items:center"><input type="checkbox" name="hold" value="1" checked style="width:22px;height:22px"> Out of spec: hold this lot</label></div>
    @if($errors->any())<div class="err">{{ $errors->first() }}</div>@endif
</div>
<div class="stick"><button class="btn" id="save">Save readings</button></div>
</form>
<script>
(function () {
    var min = {{ $spec['min'] === null ? 'null' : $spec['min'] }}, max = {{ $spec['max'] === null ? 'null' : $spec['max'] }};
    function check() {
        var bad = false;
        document.querySelectorAll('.rd').forEach(function (i) {
            var v = parseFloat(i.value), out = !isNaN(v) && ((min !== null && v < min) || (max !== null && v > max));
            i.style.borderColor = out ? '#b00020' : ''; bad = bad || out;
        });
        document.getElementById('out').hidden = !bad;
        document.getElementById('save').textContent = bad ? 'Save and hold lot' : 'Save readings';
    }
    document.addEventListener('input', check);
})();
</script>
@endsection
