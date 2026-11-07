@extends('layouts.app')

@section('title', 'Next heat')
@section('breadcrumb', 'Furnace / Next heat')

{{-- design: F4·1 next heat, F4·E nothing to melt --}}
@section('content')
<div class="fl-gloves fl-has-tabs">
    @include('floor.furnace._frame', ['active' => 'heat'])

    @if (! $next)
        <h1 style="margin:0;font-size:24px">Nothing to melt</h1>
        <p style="color:var(--fl-meta);margin:6px 0 14px">No moulds are counted ready and no order needs metal on any furnace. Heats not closed: {{ $openHeats->isEmpty() ? 'none' : $openHeats->pluck('heat_number')->join(', ') }}.</p>
        @foreach ($warnings as $w)<div class="alert alert-warning">{{ $w }}</div>@endforeach
        <x-floor.big-button empty label="Next melting day {{ $calendar['next']?->format('D j M') ?? 'not set' }}" :sub="$calendar['enabled'] ? $calendar['description'] : 'Melting days are not set in Rules.'" />
    @else
        <h1 style="margin:0;font-size:24px">Next heat · {{ $next['grade']->code }} on {{ $next['furnace']->code }}</h1>
        <p style="color:var(--fl-meta);margin:4px 0 12px">{{ today()->format('D j M') }} · metal to melt {{ \App\Support\Formats::number($next['kg'], 0) }} kg{{ $next['hours'] ? ' · about '.$next['hours'].' h' : '' }}
            @if ($count) · counted ready {{ $count['at']->format('H:i') }}{{ $count['by'] ? ' by '.$count['by'] : '' }}: {{ $count['moulds'] }} moulds @else · no count yet @endif</p>

        <div class="fl-block-head">Order · pattern <span style="float:right">Due · moulds · metal kg</span></div>
        <div class="fl-rows">
            @foreach ($next['lines'] as $line)
                <x-floor.row :title="$line['order']->production_order_number.' · '.($line['order']->product?->name ?? $line['order']->product?->code)"
                    :meta="$line['customer']" :age="($line['due']?->format('j M') ?? '—').' · '.$line['pieces'].($line['ready'] ? ' ready' : ' still to make').' · '.\App\Support\Formats::number($line['kg'], 0)"
                    :tone="$line['ready'] ? null : 'warn'" />
            @endforeach
        </div>
        @if ($others)
            <p style="color:var(--fl-meta);font-size:13.5px">{{ collect($others)->map(fn ($h, $i) => 'Heat '.($i + 2).': '.$h['grade']->code.' on '.$h['furnace']->code.', '.\App\Support\Formats::number($h['kg'], 0).' kg')->join(' · ') }} are listed under this one.</p>
        @endif
        @foreach ($warnings as $w)<div class="alert alert-warning">{{ $w }}</div>@endforeach

        <x-floor.big-button label="Start this heat" :sub="'ready metal '.\App\Support\Formats::number($next['ready_kg'], 0).' of '.\App\Support\Formats::number($next['kg'], 0).' kg'" :href="route('floor.furnace.heat.start.create')" />
    @endif
</div>
@endsection
