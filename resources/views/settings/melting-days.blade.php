@extends('layouts.app')

@section('title', 'Melting days')
@section('breadcrumb', 'Settings / Melting days')

@section('content')
<div class="page-heading"><div><h1>Melting days</h1><p>The days the furnace is lit. The Next heat page and the order screen use this to say when the next pour can be and whether an order's due date allows it.</p></div></div>

<form method="POST" action="{{ route('settings.melting-days') }}">@csrf @method('PUT')
<div class="panel"><div class="panel-body">
    @if ($errors->any())<div class="alert alert-error">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
    <div class="field"><label>How the furnace days are set</label>
        @foreach (\App\Support\MeltingCalendar::MODES as $key => $label)
            <label class="check-field" style="display:flex;gap:8px;align-items:center;font-weight:500;margin:4px 0"><input type="radio" name="mode" value="{{ $key }}" data-mode @checked(old('mode', $s['mode']) === $key) style="width:auto;min-height:0"> {{ $label }}</label>
        @endforeach
    </div>

    <div data-mode-block="WEEKDAYS" class="field" style="margin-top:14px">
        <label>The furnace is lit on</label>
        <div style="display:flex;gap:14px;flex-wrap:wrap">@foreach (\App\Support\MeltingCalendar::DAYS as $n => $name)
            <label class="check-field" style="display:inline-flex;gap:6px;align-items:center"><input type="checkbox" name="weekdays[]" value="{{ $n }}" @checked(in_array($n, (array) old('weekdays', $s['weekdays']))) style="width:auto;min-height:0"> {{ $name }}</label>
        @endforeach</div>
    </div>

    <div data-mode-block="INTERVAL" style="margin-top:14px">
        <div style="display:flex;gap:16px;flex-wrap:wrap;align-items:end">
            <div class="field"><label for="interval">Every (days)</label><input id="interval" name="interval" type="number" min="1" max="30" value="{{ old('interval', $s['interval']) }}" style="width:100px"><div class="help">2 is every other day.</div></div>
            <div class="field"><label for="anchor">A day the furnace is lit</label><input id="anchor" name="anchor" type="date" value="{{ old('anchor', $s['anchor']) }}"><div class="help">The other days count from this one.</div></div>
        </div>
        <div class="field" style="margin-top:10px"><label>Never lit on</label>
            <div style="display:flex;gap:14px;flex-wrap:wrap">@foreach (\App\Support\MeltingCalendar::DAYS as $n => $name)
                <label class="check-field" style="display:inline-flex;gap:6px;align-items:center"><input type="checkbox" name="skip[]" value="{{ $n }}" @checked(in_array($n, (array) old('skip', $s['skip']))) style="width:auto;min-height:0"> {{ $name }}</label>
            @endforeach</div>
            <div class="help">A campaign that falls on one of these days does not happen; the count carries on from the next one.</div></div>
    </div>

    <div class="field" style="margin-top:16px"><label for="after_pour_days">Days needed after the pour</label>
        <input id="after_pour_days" name="after_pour_days" type="number" min="0" max="30" value="{{ old('after_pour_days', $s['after_pour_days']) }}" style="width:100px">
        <div class="help">Cooling, shakeout, fettling and dispatch. An order due on the 25th with 1 day here must be poured by the 24th.</div></div>
    <button class="btn btn-primary" type="submit">Save melting days</button>
</div></div>
</form>

<div class="panel"><div class="panel-header"><h2>What this means now</h2></div><div class="panel-body">
    <p style="margin-top:0">{{ $description }}</p>
    @if (\App\Support\MeltingCalendar::enabled())
        <p class="muted small" style="margin-bottom:6px">Next melting days</p>
        <div style="display:flex;gap:8px;flex-wrap:wrap">@foreach ($upcoming as $d)<span class="status {{ $d->isToday() ? 'status-in-progress' : '' }}">{{ $d->isToday() ? 'Today' : $d->format('D j M') }}</span>@endforeach</div>
    @endif
</div></div>
<script>
(function () {
    var radios = document.querySelectorAll('[data-mode]'), blocks = document.querySelectorAll('[data-mode-block]');
    function show() { var mode = (document.querySelector('[data-mode]:checked') || {}).value; blocks.forEach(function (b) { b.hidden = b.dataset.modeBlock !== mode; }); }
    radios.forEach(function (r) { r.addEventListener('change', show); }); show();
})();
</script>
@endsection
