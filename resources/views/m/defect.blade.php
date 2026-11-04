@extends('m.layout')
@section('title', 'Log defect')
@section('body')
<div style="padding:14px 20px 6px"><a href="{{ route('m.defects.pick') }}" class="blue" style="font-size:14px;text-decoration:none">‹ Lots</a>
    <div style="font-size:22px;font-weight:600;margin-top:4px">Log defect</div>
    <div class="muted" style="font-size:13px"><code>{{ $lot->lot_number }}</code> · {{ $lot->product?->code }} · {{ $lot->quantity_good }} good of {{ $lot->quantity_poured }}</div></div>
<form method="POST" action="{{ route('m.defects.store', $lot) }}" enctype="multipart/form-data" data-offline data-offline-label="Defect · {{ $lot->lot_number }}" data-offline-done="{{ route('m.defects.lot', $lot) }}" style="display:flex;flex-direction:column;flex:1">@csrf
<div style="padding:8px 20px;display:flex;flex-direction:column;gap:16px;flex:1">
    <label class="field"><span>Photos: put the scale card next to the defect</span><input type="file" name="photos[]" accept="image/*" capture="environment" multiple style="width:100%;padding:12px;border:1px dashed #d9dbde;border-radius:12px"></label>
    <div><span class="cap">What is wrong?{{ $common->isNotEmpty() ? ' · most common on this part first' : '' }}</span>
        <div class="seg" style="margin-top:6px">@foreach($defects as $d)<label><input type="radio" name="quality_defect_id" value="{{ $d->id }}" required @checked(old('quality_defect_id') === $d->id)><span>{{ $d->name }}</span></label>@endforeach</div></div>
    <label class="field"><span>Where on the casting</span><input type="text" name="location" value="{{ old('location') }}" list="places" placeholder="e.g. discharge flange" autocomplete="off"><datalist id="places">@foreach($places as $p)<option value="{{ $p }}">@endforeach</datalist></label>
    <div><span class="cap">Castings with this defect</span>
        <div style="display:flex;align-items:center;gap:12px;margin-top:6px"><button type="button" class="chip" style="font:600 22px system-ui;width:56px;height:52px;cursor:pointer" data-step="-1">−</button><input id="qty" type="number" name="quantity" min="1" value="{{ old('quantity', 1) }}" inputmode="numeric" style="width:90px;height:52px;text-align:center;font-size:22px;border:1px solid #d9dbde;border-radius:12px"><button type="button" class="chip" style="font:600 22px system-ui;width:56px;height:52px;cursor:pointer" data-step="1">+</button></div></div>
    <div><span class="cap">Disposition</span><div class="seg" style="margin-top:6px">@foreach(\App\Models\QualityDefectMark::DISPOSITIONS as $k => $l)<label><input type="radio" name="disposition" value="{{ $k }}" @checked(old('disposition', 'REJECT') === $k)><span>{{ $l }}<small>{{ $k === 'REJECT' ? 'Scrap: comes off the good count' : 'Fix and re-check' }}</small></span></label>@endforeach</div></div>
    @if($errors->any())<div class="err">{{ $errors->first() }}</div>@endif
</div>
<div class="stick"><button class="btn">Log defect</button></div>
</form>
<script>document.querySelectorAll('[data-step]').forEach(function(b){b.addEventListener('click',function(){var q=document.getElementById('qty');q.value=Math.max(1,(parseInt(q.value,10)||1)+parseInt(b.dataset.step,10));});});</script>
@endsection
