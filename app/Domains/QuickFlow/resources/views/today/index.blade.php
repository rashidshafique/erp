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
        <div class="panel-header"><h2>2 · Moulding list</h2><span class="muted small">@if ($list){{ $list->list_number }} · {{ $list->status === 'DRAFT' ? 'draft' : 'released' }}@else not prepared @endif</span></div>
        @if ($list)<div class="panel-body">{{ $list->items_count }} {{ $list->items_count === 1 ? 'row' : 'rows' }} on the stations.@if ($list->status === 'DRAFT') Check it and release it to the floor.@endif</div>
        @elseif ($toMake->isEmpty())<div class="empty-state">Nothing to make. Set each station's moulds per shift on the planner.</div>
        @else<div class="panel-body">{{ $toMake->sum('moulds') }} moulds suggested from open orders.</div>@endif
        <div class="panel-body" style="display:flex;gap:8px"><a class="btn btn-small" href="{{ route('work-lists.index') }}">{{ $list ? 'Open the list' : 'Prepare the list' }}</a>
            @if ($list?->status === 'ISSUED')<a class="btn btn-small btn-primary" href="{{ route('quick.floor.index', ['plan' => today()->toDateString()]) }}">Record moulds made</a>@endif</div>
    </section>

    <section class="panel">
        <div class="panel-header"><h2>3 · Count moulds</h2><span class="muted small">{{ $mouldsWaiting->sum('expected') }} moulds on record</span></div>
        @if ($mouldsWaiting->isEmpty())<div class="empty-state">No moulds are waiting for metal.</div>@else
        <div class="table-wrap"><table><tbody>@foreach ($mouldsWaiting->take(6) as $w)
            <tr><td>{{ $w['revision']->pattern->code }} {{ $w['revision']->pattern->name }}</td><td>{{ $w['expected'] }} moulds</td></tr>
        @endforeach</tbody></table></div>@endif
        <div class="panel-body"><a class="btn btn-small" href="{{ route('mould-count.index') }}">Count before pouring</a>@if ($lastCount) <span class="muted small">last counted {{ \Illuminate\Support\Carbon::parse($lastCount)->displayShortDateTime() }}</span>@endif</div>
    </section>

    <section class="panel">
        <div class="panel-header"><h2>4 · Heat</h2>@if ($nextHeat)<span class="muted small">next: {{ $nextHeat['grade']->code }} on {{ $nextHeat['furnace']->code }}</span>@endif</div>
        @if ($openHeats->isNotEmpty())<div class="panel-body">Running: @foreach ($openHeats as $h)<a href="{{ route('heats.show', $h) }}#close-heat">{{ $h->heat_number }}</a>@if (! $loop->last), @endif @endforeach · close each when the furnace is off.</div>@endif
        @if (! $nextHeat)<div class="empty-state">No metal needed for open orders.</div>@else
        <div class="panel-body"><strong>{{ \App\Support\Formats::number($nextHeat['kg'], 0) }} kg</strong> for {{ count($nextHeat['lines']) }} {{ count($nextHeat['lines']) === 1 ? 'order' : 'orders' }}@if ($nextHeat['hours']), about {{ $nextHeat['hours'] }} h to melt @endif</div>@endif
        <div class="panel-body"><a class="btn btn-small" href="{{ route('quick.heat.index') }}">Plan, start and close heats</a></div>
    </section>

    <section class="panel">
        <div class="panel-header"><h2>5 · Moulds waiting to pour</h2><span class="muted small">{{ $awaiting->sum('awaiting') }} pieces</span></div>
        @if ($awaiting->isEmpty())<div class="empty-state">No moulds are waiting.</div>@else
        <div class="table-wrap"><table><tbody>@foreach ($awaiting as $o)
            <tr><td>{{ $o['order']->production_order_number }} {{ $o['order']->product?->code }}</td><td>{{ $o['grade']?->code }}</td><td>{{ $o['awaiting'] }} pcs</td></tr>
        @endforeach</tbody></table></div>@endif
        <div class="panel-body"><a class="btn btn-small btn-primary" href="{{ route('quick.floor.index') }}#poured">Record moulds poured</a></div>
    </section>

    <section class="panel">
        <div class="panel-header"><h2>6 · After pour</h2><span class="muted small">{{ $afterPour->sum() }} pieces between pour and QC</span></div>
        @if ($afterPour->isEmpty())<div class="empty-state">Nothing to knock out, fettle or clean.</div>@else
        <div class="table-wrap"><table><tbody>@foreach ($afterPour as $step => $pieces)<tr><td>{{ $step }}</td><td>{{ $pieces }} pcs</td></tr>@endforeach</tbody></table></div>@endif
        <div class="panel-body"><a class="btn btn-small" href="{{ route('lots.queue') }}">Production queue</a></div>
    </section>

    <section class="panel">
        <div class="panel-header"><h2>7 · Castings to check</h2><span class="muted small">{{ $piecesToCheck }} pieces in {{ $toCheck->count() }} {{ $toCheck->count() === 1 ? 'lot' : 'lots' }}</span></div>
        @if ($toCheck->isEmpty())<div class="empty-state">Nothing waiting for QC.</div>@else
        <div class="table-wrap"><table><tbody>@foreach ($toCheck->take(6) as $lot)
            <tr><td><a href="{{ route('lots.show', $lot) }}">{{ $lot->code ?? $lot->lot_number }}</a></td><td>{{ $lot->product?->code }}</td><td>{{ $lot->to_check }} pcs</td></tr>
        @endforeach</tbody></table></div>@endif
        <div class="panel-body"><a class="btn btn-small" href="{{ route('quick-qc.index') }}">Quick QC</a></div>
    </section>

    @if ($toPack !== null)
    <section class="panel">
        <div class="panel-header"><h2>8 · Ready to go out</h2></div>
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
