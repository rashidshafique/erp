@extends('layouts.app')

@section('title', $heat->heat_number)
@section('breadcrumb', 'Furnace / '.$heat->heat_number)

{{-- design: F4·3 charges (one overdue), F4·W2 no melting rate --}}
@section('content')
@php
    $closed = in_array($heat->status, ['CLOSED', 'CANCELLED'], true);
    $kgFmt = fn ($v) => \App\Support\Formats::number((float) $v, 0);
@endphp
<div class="fl-gloves fl-has-tabs">
    @include('floor.furnace._frame', ['active' => 'heat'])

    <h1 style="margin:0;font-size:22px">{{ $heat->heat_number }} · {{ $closed ? strtolower($heat->status) : 'charging' }} {{ $done }} of {{ $planned }} · {{ $kgFmt($chargedKg) }} of {{ $kgFmt($heat->target_metal_weight_kg) }} kg{{ $finish ? ' · finish about '.$finish['finish_at']->format('H:i') : '' }}</h1>
    <p style="color:var(--fl-meta);font-size:13.5px;margin:4px 0 12px">{{ $heat->furnace?->code }} · {{ $heat->ironGrade?->code }} · {{ $heat->status_label ?? ucfirst(strtolower(str_replace('_', ' ', $heat->status))) }}</p>

    @if ($noRate)
        <x-floor.blocker state="warn" title="Set the furnace melting rate to get a schedule"
            :detail="$heat->furnace?->code.' has no measured rate for this heat, no recorded average and no rated speed, so charge due times cannot be worked out. Charges can still be entered.'"
            action="Set rate for {{ $heat->furnace?->code }}" :href="route('furnaces.show', $heat->furnace)" />
    @endif

    @unless ($closed)
        @if ($big['kind'] === 'charge')
            <form method="POST" action="{{ route('heats.charge-rounds.confirm', $heat) }}">@csrf
                <x-floor.big-button :tone="$big['minutes'] > 0 ? 'warn' : null" :label="$big['label'].' as recipe'" :sub="$big['sub']" />
            </form>
            <a class="fl-btn" style="margin-top:10px;min-height:56px;display:flex;align-items:center;justify-content:center" href="{{ route('floor.furnace.heat.charge.edit', [$heat, $big['round']]) }}">{{ $big['label'] }}, different kg</a>
        @elseif ($big['kind'] === 'test')
            <x-floor.big-button tone="warn" :label="$big['label']" :sub="$big['sub']" :href="route('heats.show', $heat)" />
        @else
            <x-floor.big-button :label="$big['label']" :sub="$big['sub']" :href="route('floor.furnace.heat.pour.gate', $heat)" />
        @endif
    @endunless

    <div class="fl-block-head">Charge <span style="float:right">Due · done</span></div>
    <div class="fl-rows">
        @forelse ($chargeRows as $row)
            <x-floor.row :title="$row['round'] === 0 ? 'Start-up' : '#'.$row['round']"
                :meta="$row['recorded_at'] ? $row['differences'] : ($row['state'] === 'projected' ? ($row['due_at'] ? 'after the one before' : '') : '')"
                :age="($row['due_at']?->format('H:i') ?? '—').' · '.($row['recorded_at']?->format('H:i') ?? '—')"
                :tone="$row['state'] === 'due' ? 'warn' : null"
                :href="$row['recorded_at'] ? route('floor.furnace.heat.charge.edit', [$heat, $row['round']]) : null" />
        @empty
            <div class="fl-row-empty">This heat has no charges planned.</div>
        @endforelse
    </div>
    <p style="color:var(--fl-meta);font-size:13.5px">A wrong entry is fixed by tapping its row and giving a reason. Nothing is deleted.</p>

    <div class="fl-rows">
        <x-floor.row title="Melt rate {{ $plan['rate'] ? $kgFmt($plan['rate']['rate']).' kg/h' : '—' }}" :meta="$plan['rate']['label'] ?? 'no rate on record'" />
        <x-floor.row title="Metal tapped {{ $heat->actual_tapped_weight_kg !== null ? $kgFmt($heat->actual_tapped_weight_kg).' kg' : '0 kg' }}" />
        <x-floor.row :title="$lastSample ? 'Last sample '.$lastSample['at']->format('H:i').' · '.$lastSample['result'] : 'No sample yet'"
            :meta="$sampleDue ? ($sampleDue['minutes_since'].' min into the run · a sample every '.$sampleDue['interval'].' min') : 'chemistry and thermal test'"
            :tone="$sampleDue && $sampleDue['overdue'] ? 'warn' : null" action="Record test" :href="route('heats.show', $heat)" />
        <x-floor.row title="Furnace readings" :meta="$readings->isNotEmpty() ? 'last '.$readings->first()->recorded_at->format('H:i') : 'none yet'" action="Readings" :href="route('heats.show', $heat)" />
    </div>

    @unless ($closed)
        <div style="display:grid;gap:10px;margin-top:12px">
            @if ($big['kind'] !== 'pour')<a class="fl-btn" style="min-height:56px;display:flex;align-items:center;justify-content:center" href="{{ route('floor.furnace.heat.pour.gate', $heat) }}">Pour moulds <span style="color:var(--fl-meta);margin-left:8px">once metal is tapped</span></a>@endif
            <a class="fl-btn" style="min-height:56px;display:flex;align-items:center;justify-content:center" href="{{ route('floor.furnace.heat.close', $heat) }}">Close heat <span style="color:var(--fl-meta);margin-left:8px">after the last pour</span></a>
        </div>
    @endunless
</div>
@endsection
