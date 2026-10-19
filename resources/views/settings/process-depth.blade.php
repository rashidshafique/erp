@extends('layouts.app')

@section('title', 'Process depth')
@section('breadcrumb', 'Settings / Process depth')

@section('content')
<div class="page-heading">
    <div><h1>Process depth</h1><p>How much checking the shop floor goes through. This sets the individual rules for you; each stays editable on its own settings page.</p></div>
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
    <p class="muted small" style="margin:16px 0 0">Rules set: pouring checks and first-iron and containment holds (Pouring rules), contract review and when an order completes (Order rules), and overdue instrument use (Calibration rules).</p>
</div></div>
@endsection
