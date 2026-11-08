@extends('layouts.app')

@section('title', $lot->label)
@section('breadcrumb', 'Quality / '.$lot->label)

{{-- design: Q·3 the lot: what blocks dispatch. Failed first, then passed. --}}
@section('content')
<div class="fl-tablet fl-has-tabs">
    @include('floor.quality._frame', ['active' => 'lots'])

    <h1 style="margin:0;font-size:22px">{{ $lot->label }} · {{ $lot->product?->name }} <x-floor.chip :status="$lot->status" /></h1>
    <p style="color:var(--fl-meta);font-size:13.5px;margin:4px 0 10px">{{ $lot->productionOrder?->production_order_number }}@if ($rule['customer']) · {{ $rule['customer'] }}@endif @if ($heat) · {{ $heat->heat_number }}@endif · {{ $lot->quantity_poured }} poured · {{ $accepted }} accepted</p>
    @if ($lot->quality_hold)<p class="alert alert-warning">{{ $lot->quality_hold }}. It clears by itself when its NCRs are closed.</p>@endif

    @php($failed = collect($ready['checks'])->where('ok', false))
    <div class="fl-block-head">What blocks dispatch</div>
    @if ($failed->isEmpty())
        <x-floor.blocker state="clear" title="Nothing blocks this lot" />
    @endif
    @foreach ($failed->concat(collect($ready['checks'])->where('ok', '!==', false)) as $c)
        @if ($c['ok'] === false && $c['label'] === 'Final inspection' && $toCheck > 0)
            <x-floor.blocker state="fail" :title="$c['label']" :detail="$c['detail']" action="Inspect" :href="route('floor.quality.lot.inspect', $lot)" />
        @else
            <x-floor.blocker :state="$c['ok'] === false ? 'fail' : ($c['ok'] === null ? 'warn' : 'pass')" :title="$c['label']" :detail="$c['detail']" :who="$c['ok'] === false ? 'See the lot page' : null" />
        @endif
    @endforeach

    <div class="fl-rows">
        <x-floor.row plain title="Customer rule" :meta="$rule['order'] ? $rule['order'].' · partial shipments '.($rule['partial'] ? 'allowed' : 'not allowed').($rule['min'] ? ' · fewest to ship '.$rule['min'].' pcs' : '') : 'Stock lot · an open NCR holds the whole lot'" />
    </div>
    <p><a class="fl-btn" href="{{ route('lots.show', $lot) }}">Full lot page</a> <a class="fl-btn" href="{{ route('lots.traveller', $lot) }}">Traveller</a></p>
</div>
@endsection
