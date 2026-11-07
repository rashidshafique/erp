@extends('layouts.app')

@section('title', 'Enter moulds')
@section('breadcrumb', 'Moulding / Enter moulds')

{{-- design: F3·2 enter moulds made (Full). Numbers typed are added to the run; the first save starts it. --}}
@section('content')
@php
    $pattern = $item->patternRevision->pattern;
    $sound = (int) ($run?->accepted_mould_qty ?? 0);
@endphp
<div class="fl-tablet fl-has-tabs">
    @include('floor.moulding._frame')

    <h1 style="margin:0;font-size:22px"><span class="fl-mono">{{ $pattern->code }}</span> {{ $pattern->name }}</h1>
    <p style="color:var(--fl-meta);font-size:13.5px;margin:4px 0 12px">{{ $item->productionOrder?->production_order_number }} · {{ $item->station?->code }} · {{ $item->workList->list_date->format('D j M') }}{{ $item->team ? ' · '.$item->team->name : '' }}</p>

    <div class="fl-rows" style="margin-bottom:16px">
        @if ($run)
            <div class="fl-row" style="grid-template-columns:minmax(0,1fr) auto">
                <span><span class="fl-row-title">Run <span class="fl-mono">{{ $run->run_number }}</span> · started {{ $run->started_at?->format('H:i') }}</span>
                    <span class="fl-row-meta">{{ $run->team_name ?? $item->team?->name }} · {{ $run->sand_system ?? $item->sand_system }}</span></span>
                @if ($run->status === 'IN_PROGRESS')
                    <form method="POST" action="{{ route('moulding-runs.status', $run) }}">@csrf<input type="hidden" name="status" value="ON_HOLD"><button class="fl-btn">Put on hold</button></form>
                @else
                    <x-floor.chip :status="$run->status === 'ON_HOLD' ? 'ON_HOLD' : 'CLOSED'" />
                @endif
            </div>
        @else
            <div class="fl-row-empty">The first save starts the run on this row.</div>
        @endif
        <div class="fl-row" style="grid-template-columns:minmax(0,1fr) auto">
            <span class="fl-row-title">Cavities (from the cavity map)</span><span class="fl-row-title">{{ $cavities ? $cavities.' per mould' : 'not mapped' }}</span>
        </div>
        <div class="fl-row" style="grid-template-columns:minmax(0,1fr) auto">
            <span class="fl-row-title">Already made</span><span class="fl-row-title">{{ $sound }} of {{ $item->planned_moulds }}</span>
        </div>
    </div>

    <form method="POST" action="{{ route('floor.moulding.save', $item) }}">@csrf
        <div class="fl-count-pad">
            <div>
                <x-floor.count-field name="made" label="Moulds made now" :value="old('made')" :hint="'Plan '.$item->planned_moulds.', '.max(0, $item->planned_moulds - $sound).' still to make.'" />
                <x-floor.count-field name="damaged" label="Damaged" :value="old('damaged', 0)" hint="Out of the moulds made now." />
                <x-floor.count-field name="downtime" label="Downtime (min)" :value="old('downtime', 0)" />
                <label class="fl-count"><span class="fl-count-label">Downtime reason</span>
                    <input name="downtime_reason" type="text" maxlength="2000" value="{{ old('downtime_reason') }}" placeholder="none" style="height:56px;font-size:16px;text-align:left;font-weight:400" data-no-unsaved>
                </label>
                <button type="submit" name="complete" value="1" class="fl-btn" style="min-height:56px;width:100%">Save and complete run</button>
            </div>
            <x-floor.keypad save="Save moulds" />
        </div>
    </form>
</div>
@endsection
