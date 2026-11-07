@extends('layouts.app')

@section('title', 'Start heat')
@section('breadcrumb', 'Furnace / Start heat')

{{-- design: F4·2 start with the furnace's recipe; a kilogram changed here is for this heat only --}}
@section('content')
@php($items = $recipe ? $recipe->items->where('charge_stage', 'ROUND')->values() : collect())
<div class="fl-gloves fl-has-tabs">
    @include('floor.furnace._frame', ['active' => 'heat'])

    <h1 style="margin:0;font-size:24px">Start heat · {{ $next['furnace']->code }} {{ $next['grade']->code }}</h1>
    <p style="color:var(--fl-meta);margin:4px 0 12px">Operator: {{ auth()->user()?->name ?? 'you' }}</p>

    <form method="POST" action="{{ route('floor.furnace.heat.start') }}">
        @csrf
        <input type="hidden" name="furnace_id" value="{{ $next['furnace']->id }}">
        <input type="hidden" name="iron_grade_id" value="{{ $next['grade']->id }}">
        <input type="hidden" name="kg" value="{{ $next['kg'] }}">
        <input type="hidden" name="operator_name" value="{{ auth()->user()?->name }}">
        @if ($recipe)<input type="hidden" name="recipe_id" value="{{ $recipe->id }}">@endif

        <div class="fl-block-head">Charge recipe · {{ $recipe?->code ?? 'none' }} · per charge</div>
        @if ($recipes->count() > 1)
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px">
                @foreach ($recipes as $r)
                    <a class="fl-btn {{ $r->id === $recipe?->id ? 'is-primary' : '' }}" href="{{ route('floor.furnace.heat.start.create', ['heat' => $index, 'anyway' => 1, 'recipe_id' => $r->id]) }}">{{ $r->code }}</a>
                @endforeach
            </div>
        @endif
        @if ($recipe)
            <div class="fl-rows">
                @foreach ($items as $item)
                    <div class="fl-row" style="display:flex;justify-content:space-between;align-items:center;gap:12px">
                        <label for="kg-{{ $item->id }}" style="flex:1">{{ $item->material?->name }}</label>
                        <input id="kg-{{ $item->id }}" type="number" step="any" min="0" name="charge_kg[{{ $item->id }}]" value="{{ (float) $item->perChargeKg() }}" style="min-height:56px;width:120px;font-size:20px;text-align:right"> kg
                    </div>
                @endforeach
            </div>
            <p style="margin:10px 0 0"><strong>Metal per charge {{ \App\Support\Formats::number($perCharge, 0) }} kg</strong> · target {{ \App\Support\Formats::number($next['kg'], 0) }} kg · {{ $charges ?? '—' }} charges</p>
            <p style="color:var(--fl-meta);font-size:13.5px;margin:4px 0 12px">
                @if ($interval)Schedule: {{ \App\Support\Formats::number($perCharge, 0) }} kg ÷ {{ \App\Support\Formats::number($rate, 0) }} kg/h = one charge every {{ $interval }} min.@else No melting rate is set for {{ $next['furnace']->code }}, so charges have no due times. @endif
                Tap a kg box to change it for this heat only. The recipe itself does not change.</p>
        @else
            <div class="alert alert-warning">{{ $next['furnace']->code }} has no active charge recipe. The heat can start; charges are entered by hand.</div>
        @endif

        <div style="display:grid;gap:12px">
            <x-floor.big-button label="Start heat" :sub="'blast on now'.($next['furnace']->furnace_type === 'CUPOLA' ? '' : ' · furnace lit')" />
            <button type="submit" name="light_only" value="1" class="fl-btn">Light-up now (blast on later)</button>
        </div>
    </form>
</div>
@endsection
