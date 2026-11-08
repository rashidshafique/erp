@extends('layouts.app')

@section('title', 'Lots to check')
@section('breadcrumb', 'Quality / Lots')

{{-- design: Q·2 lots after the pour, and what blocks each lot at final inspection. --}}
@section('content')
<div class="fl-tablet fl-has-tabs">
    @include('floor.quality._frame', ['active' => 'lots'])

    <h1 style="margin:0;font-size:22px">Lots after the pour</h1>
    <div class="fl-rows">
        @foreach ($steps as $label => $n)<x-floor.row :title="$label" :age="(string) $n" plain />@endforeach
    </div>

    <div class="fl-block-head">Final inspection · what blocks each lot</div>
    <div class="fl-rows">
        @forelse ($final as $lot)
            <x-floor.row :title="$lot->label.' · '.$lot->product?->name" :meta="($lot->productionOrder?->production_order_number ?? 'no order').' · '.$lot->quantity_poured.' pcs'.($lot->blocks ? ' · ✗ '.implode(' · ', $lot->blocks) : '')"
                :age="$lot->created_at?->diffForHumans(null, true)" :href="route('floor.quality.lot', $lot)" action="Open" />
        @empty
            <div class="fl-row-empty">No lots are waiting for inspection. Lots appear here after each pour.@if ($ncrs) <a href="{{ route('ncrs.index') }}">{{ $ncrs }} open NCR(s)</a>@endif</div>
        @endforelse
    </div>
</div>
@endsection
