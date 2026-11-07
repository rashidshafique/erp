@extends('layouts.app')

@section('title', 'Ready to pour')
@section('breadcrumb', 'Moulding / Count / Ready')

{{-- design: F3·5 ready to pour (Full): what the count left for the heat, and whether it fits. --}}
@section('content')
@php
    $moulds = collect($rows)->sum('moulds');
    $kg = collect($rows)->every(fn ($r) => $r['kg'] !== null) ? collect($rows)->sum('kg') : null;
    $capacity = $heat ? ((float) $heat->target_metal_weight_kg ?: (float) $heat->furnace?->rated_capacity_kg) : 0;
@endphp
<div class="fl-tablet fl-has-tabs">
    @include('floor.moulding._frame')

    <h1 style="margin:0;font-size:22px">{{ $moulds }} moulds ready{{ $heat ? ' for '.$heat->heat_number : '' }} <x-floor.chip status="READY" /></h1>
    <p style="color:var(--fl-meta);font-size:13.5px;margin:4px 0 12px">Counted {{ $at }}{{ $by ? ' by '.$by : '' }}{{ $written_off ? ' · '.$written_off.' written off' : '' }} · shown on the melt shop's next heat.</p>

    <div class="table-wrap"><table>
        <thead><tr><th>Pattern</th><th class="num">Moulds</th><th class="num">Castings</th><th class="num">Metal kg</th></tr></thead>
        <tbody>
            @foreach ($rows as $r)
                <tr><td><span class="fl-mono">{{ $r['code'] }}</span> {{ $r['name'] }}</td><td class="num">{{ $r['moulds'] }}</td>
                    <td class="num">{{ $r['castings'] ?? '—' }}</td><td class="num">{{ $r['kg'] !== null ? number_format($r['kg'], 1) : '—' }}</td></tr>
            @endforeach
        </tbody>
        <tfoot><tr><th>Total</th><th class="num">{{ $moulds }}</th><th class="num">{{ collect($rows)->sum('castings') ?: '—' }}</th><th class="num">{{ $kg !== null ? number_format($kg, 1) : '—' }}</th></tr></tfoot>
    </table></div>

    <div class="fl-blockers" style="margin-top:16px">
        @if ($kg === null)
            <x-floor.blocker state="warn" title="Metal not known for every pattern" detail="Add casting weights to the cavity map so the melt can be sized." />
        @elseif ($capacity > 0 && $kg > $capacity)
            <x-floor.blocker state="warn" :title="'More than the heat holds · '.number_format($kg, 1).' kg ready'" :detail="'The heat is '.number_format($capacity, 0).' kg; the rest waits for the next heat.'" />
        @elseif ($capacity > 0)
            <x-floor.blocker state="pass" :title="'Fits the '.$heat->furnace?->code.' heat of '.number_format($capacity, 0).' kg'" :detail="number_format($kg, 1).' kg ready'" />
        @else
            <x-floor.blocker state="pass" :title="number_format($kg, 1).' kg ready'" detail="No heat size to compare with yet." />
        @endif
    </div>

    <div style="display:flex;gap:12px;margin-top:16px">
        <a href="{{ route('floor.moulding.count') }}" class="fl-btn" style="min-height:56px">Recount</a>
        <a href="{{ route('home') }}" class="fl-btn is-primary" style="min-height:56px;flex:1">Done · back to Home</a>
    </div>
</div>
@endsection
