@extends('layouts.app')

@section('title', 'Charge '.$round)
@section('breadcrumb', 'Furnace / '.$heat->heat_number.' / Charge '.$round)

{{-- design: F4·3 secondary path. A charge at different kg, or a recorded charge corrected with a reason (never deleted). --}}
@section('content')
@php($label = $round === 0 ? 'Start-up charge' : 'Charge #'.$round)
<div class="fl-gloves fl-has-tabs">
    @include('floor.furnace._frame', ['active' => 'heat'])

    @if ($entries)
        <h1 style="margin:0;font-size:22px">{{ $heat->heat_number }} · fix {{ strtolower($label) }}</h1>
        <p style="color:var(--fl-meta);font-size:13.5px">The entry stays on the record with the change and your reason.</p>
        <form method="POST" action="{{ route('floor.furnace.heat.charge.correct', [$heat, $round]) }}">@csrf
            <div class="fl-rows">
                @foreach ($entries as $e)
                    <div class="fl-row" style="display:flex;justify-content:space-between;align-items:center;gap:12px">
                        <label for="e-{{ $e->id }}" style="flex:1">{{ $e->material?->name }}</label>
                        <input id="e-{{ $e->id }}" type="number" step="any" min="0.001" name="entries[{{ $e->id }}]" value="{{ (float) $e->actual_qty_kg }}" style="min-height:56px;width:120px;font-size:20px;text-align:right"> kg
                    </div>
                @endforeach
            </div>
            <label for="reason" class="fl-block-head" style="display:block">Why was it wrong?</label>
            <input id="reason" name="reason" required minlength="3" maxlength="500" style="min-height:56px;width:100%;font-size:18px" placeholder="e.g. steel weighed twice">
            <div style="margin-top:12px"><x-floor.big-button label="Save the fix" sub="the reason is kept with the entry" /></div>
        </form>
    @else
        <h1 style="margin:0;font-size:22px">{{ $heat->heat_number }} · {{ strtolower($label) }}, different kg</h1>
        <form method="POST" action="{{ route('floor.furnace.heat.charge.store', [$heat, $round]) }}">@csrf
            <div class="fl-rows">
                @foreach ($items as $i => $item)
                    <div class="fl-row" style="display:flex;justify-content:space-between;align-items:center;gap:12px">
                        <label for="l-{{ $i }}" style="flex:1">{{ $item->material?->name }}</label>
                        <input type="hidden" name="lines[{{ $i }}][material_id]" value="{{ $item->material_id }}">
                        <input id="l-{{ $i }}" type="number" step="any" min="0" name="lines[{{ $i }}][actual_qty_kg]" value="{{ $heat->chargeKgFor($item) }}" style="min-height:56px;width:120px;font-size:20px;text-align:right"> kg
                    </div>
                @endforeach
            </div>
            <div style="margin-top:12px"><x-floor.big-button :label="$label.' recorded'" sub="at the time you tap" /></div>
        </form>
    @endif
</div>
@endsection
