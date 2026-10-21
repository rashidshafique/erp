@extends('layouts.app')

@section('title', 'Process depth')
@section('breadcrumb', 'Settings / Process depth')

@section('content')
<div class="page-heading">
    <div><h1>Process depth</h1><p>How much checking the shop floor goes through. A preset is only a starting point: it fills in every rule below, and each rule can then be set on its own.</p></div>
</div>
<div class="panel"><div class="panel-body">
    @if ($current === 'CUSTOM')
        <p class="muted" style="margin-top:0">Your rules are currently a custom mix. Choosing a preset replaces them.</p>
    @endif
    <div class="rule-grid">
        @foreach ($presets as $key => [$label, $description])
            <form method="POST" action="{{ route('settings.process-depth') }}" @if ($current !== $key) onsubmit="return confirm('Apply {{ $label }} process depth? This changes the pouring, order, contract review and calibration rules.')" @endif>
                @csrf @method('PUT')
                <input type="hidden" name="preset" value="{{ $key }}">
                <h2 style="margin-top:0">{{ $label }} @if ($current === $key)<span class="status">In use</span>@endif</h2>
                <p>{{ $description }}</p>
                <button class="btn {{ $current === $key ? '' : 'btn-primary' }}" type="submit">{{ $current === $key ? 'Re-apply' : 'Use '.$label }}</button>
            </form>
        @endforeach
    </div>
</div></div>
<form method="POST" action="{{ route('settings.process-depth') }}">
    @csrf @method('PUT')
    <div class="panel"><div class="panel-body">
        <h2 style="margin-top:0">Each rule</h2>
        <div class="rule-grid">
            @foreach ($rules as $key => [$label, $options])
                <fieldset>
                    <legend>{{ $label }}</legend>
                    @foreach ($options as $value => $text)<label><input type="radio" name="{{ $key }}" value="{{ $value }}" @checked(old($key, $values[$key]) === $value)> {{ $text }}</label>@endforeach
                </fieldset>
            @endforeach
        </div>
    </div></div>
    <div class="actions"><button class="btn btn-primary" type="submit">Save rules</button></div>
</form>
@endsection
