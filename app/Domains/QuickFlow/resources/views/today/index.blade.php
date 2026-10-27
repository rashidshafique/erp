@extends('layouts.app')

@section('title', 'Today')
@section('breadcrumb', 'Quick flow / Today')

@section('content')
<div class="page-heading">
    <div><h1>Today</h1><p>{{ today()->format('l j F') }}. The day's work in the order it flows, each with the step that does it.</p></div>
    <div class="actions"><a class="btn btn-primary" href="{{ route('quick.orders.create') }}">+ New order</a></div>
</div>

<div class="today-grid">
    <section class="panel">
        <div class="panel-header"><h2>1 · Orders due</h2><span class="muted small">{{ $due->count() }} open, soonest first</span></div>
        @if ($due->isEmpty())<div class="empty-state">No open orders.</div>@else
        <div class="table-wrap"><table><tbody>@foreach ($due as $o)
            <tr><td><a href="{{ route('production-orders.show', $o) }}">{{ $o->production_order_number }}</a> {{ $o->product?->code }}</td><td>{{ $o->customerLabel() }}</td>
                <td class="{{ $o->required_date?->lt(today()) ? 'text-danger' : '' }}">{{ $o->required_date?->displayShortDate() ?? '—' }}</td></tr>
        @endforeach</tbody></table></div>@endif
        <div class="panel-body"><a class="btn btn-small" href="{{ route('quick.orders.create') }}">New order</a></div>
    </section>

    <section class="panel">
        <div class="panel-header"><h2>2 · Moulds to make</h2><span class="muted small">{{ $toMake->sum('moulds') }} moulds planned this shift</span></div>
        @if ($toMake->isEmpty())<div class="empty-state">Nothing planned. Set each station's moulds per shift on the planner.</div>@else
        <div class="table-wrap"><table><tbody>@foreach ($toMake as $r)
            <tr><td>{{ $r['pattern']->pattern?->name ?? $r['pattern']->code }}</td><td>{{ $r['order']->production_order_number }}</td><td>{{ $r['station']->code }}</td><td>{{ $r['moulds'] }}</td></tr>
        @endforeach</tbody></table></div>@endif
        <div class="panel-body"><a class="btn btn-small btn-primary" href="{{ route('quick.floor.index', ['plan' => today()->toDateString()]) }}">Record moulds made</a></div>
    </section>

    <section class="panel">
        <div class="panel-header"><h2>3 · Next heat</h2>@if ($nextHeat)<span class="muted small">{{ $nextHeat['grade']->code }} on {{ $nextHeat['furnace']->code }}</span>@endif</div>
        @if (! $nextHeat)<div class="empty-state">No metal needed for open orders.</div>@else
        <div class="panel-body"><strong>{{ \App\Support\Formats::number($nextHeat['kg'], 0) }} kg</strong> for {{ count($nextHeat['lines']) }} {{ count($nextHeat['lines']) === 1 ? 'order' : 'orders' }}@if ($nextHeat['hours']), about {{ $nextHeat['hours'] }} h to melt @endif</div>@endif
        <div class="panel-body"><a class="btn btn-small" href="{{ route('quick.heat.index') }}">Plan and start the heat</a></div>
    </section>

    <section class="panel">
        <div class="panel-header"><h2>4 · Moulds waiting to pour</h2><span class="muted small">{{ $awaiting->sum('awaiting') }} pieces</span></div>
        @if ($awaiting->isEmpty())<div class="empty-state">No moulds are waiting.</div>@else
        <div class="table-wrap"><table><tbody>@foreach ($awaiting as $o)
            <tr><td>{{ $o['order']->production_order_number }} {{ $o['order']->product?->code }}</td><td>{{ $o['grade']?->code }}</td><td>{{ $o['awaiting'] }} pcs</td></tr>
        @endforeach</tbody></table></div>@endif
        <div class="panel-body"><a class="btn btn-small btn-primary" href="{{ route('quick.floor.index') }}#poured">Record moulds poured</a></div>
    </section>

    <section class="panel">
        <div class="panel-header"><h2>5 · Castings to check</h2><span class="muted small">{{ $piecesToCheck }} pieces in {{ $toCheck->count() }} {{ $toCheck->count() === 1 ? 'lot' : 'lots' }}</span></div>
        @if ($toCheck->isEmpty())<div class="empty-state">Nothing waiting for QC.</div>@else
        <div class="table-wrap"><table><tbody>@foreach ($toCheck->take(6) as $lot)
            <tr><td><a href="{{ route('lots.show', $lot) }}">{{ $lot->code ?? $lot->lot_number }}</a></td><td>{{ $lot->product?->code }}</td><td>{{ $lot->to_check }} pcs</td></tr>
        @endforeach</tbody></table></div>@endif
        <div class="panel-body"><a class="btn btn-small" href="{{ route('quick-qc.index') }}">Quick QC</a></div>
    </section>

    @if ($toPack !== null)
    <section class="panel">
        <div class="panel-header"><h2>6 · Ready to go out</h2></div>
        <div class="panel-body"><strong>{{ $toPack }}</strong> {{ $toPack === 1 ? 'lot' : 'lots' }} to pack · <strong>{{ $toDispatch }}</strong> packed, to dispatch</div>
        <div class="panel-body" style="display:flex;gap:8px"><a class="btn btn-small" href="{{ route('packing.index') }}">Pack</a><a class="btn btn-small" href="{{ route('dispatches.index') }}">Dispatch</a></div>
    </section>
    @endif
</div>

@if ($attention)
<div class="panel"><div class="panel-header"><h2>Needs attention</h2></div>
    <div class="table-wrap"><table><tbody>@foreach ($attention as $item)
        <tr><td><span class="status">{{ $item['tag'] }}</span></td><td>{{ $item['ref'] }} {{ $item['text'] }}</td>
            <td>@foreach ($item['actions'] as [$label, $url])<a class="btn btn-small" href="{{ $url }}">{{ $label }}</a>@endforeach</td></tr>
    @endforeach</tbody></table></div>
</div>
@endif

@push('styles')
<style>
    .today-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 16px; margin-bottom: 16px; }
    .today-grid .panel { margin: 0; display: flex; flex-direction: column; }
    .today-grid .panel > .panel-body:last-child { margin-top: auto; }
</style>
@endpush
@endsection
