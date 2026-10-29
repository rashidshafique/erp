@extends('layouts.app')

@section('title', 'Moulding list')
@section('breadcrumb', 'Quick flow / Moulding list')

@php
    $isToday = $day->isToday();
    $editable = $list && in_array($list->status, ['DRAFT', 'ISSUED'], true);
@endphp

@section('content')
@include('partials.search-select')
<div class="page-heading">
    <div><h1>Moulding list · {{ $isToday ? 'today' : ($day->isTomorrow() ? 'tomorrow' : $day->displayDate()) }}</h1>
        <p>What to mould, on which station, from what open orders still need. Change the rows, then release it to the floor.</p></div>
    <div class="actions">
        <a class="btn @if ($isToday) btn-primary @endif" href="{{ route('quick.list.index') }}">Today</a>
        <a class="btn @if ($day->isTomorrow()) btn-primary @endif" href="{{ route('quick.list.index', ['day' => today()->addDay()->toDateString()]) }}">Tomorrow</a>
    </div>
</div>
@if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if (session('warning'))<div class="alert alert-warning">{{ session('warning') }}</div>@endif
@if ($errors->has('item'))<div class="alert alert-error">{{ $errors->first('item') }}</div>@endif

@if (! $list)
<div class="panel"><div class="panel-body">
    <p>No list for this day yet. Preparing it takes what each open order still needs, after the moulds already made, spreads it over the stations, and carries over rows left unstarted on earlier lists.</p>
    @if ($day->gte(today()))
    <form method="POST" action="{{ route('work-lists.prepare') }}">@csrf<input type="hidden" name="day" value="{{ $day->toDateString() }}"><input type="hidden" name="stay" value="1">
        <button class="btn btn-primary">Prepare list</button></form>
    @endif
</div></div>
@else
<div class="panel">
    <div class="panel-header"><h2>{{ $list->list_number }} · {{ $list->status === 'DRAFT' ? 'Draft' : ($list->status === 'ISSUED' ? 'Released' : ucfirst(strtolower($list->status))) }}</h2>
        <div style="display:flex;gap:8px">
            @if ($list->status === 'DRAFT')
                <form method="POST" action="{{ route('work-lists.issue', $list) }}">@csrf<input type="hidden" name="issued_by" value="{{ auth()->user()?->name ?? 'Floor' }}"><button class="btn btn-primary">Release to the floor</button></form>
            @elseif ($list->status === 'ISSUED')
                <a class="btn btn-primary" href="{{ route('quick.floor.index', ['plan' => $day->toDateString()]) }}">Enter moulds made</a>
            @endif
        </div></div>
    <div class="table-wrap"><table><thead><tr><th>Station</th><th>Pattern</th><th>Order</th><th>Moulds</th><th>Made</th><th></th></tr></thead><tbody>
    @forelse ($list->items as $i)
        @php $open = $editable && $i->status === 'PLANNED' && ! $i->moulding_run_id; @endphp
        <tr>
            <td>@if ($open)<select name="moulding_station_id" form="row-{{ $i->id }}" aria-label="Station for {{ $i->patternRevision->pattern->code }}">@foreach ($stations as $s)<option value="{{ $s->id }}" @selected($s->id === $i->moulding_station_id)>{{ $s->code }}</option>@endforeach</select>@else{{ $i->station?->code }}@endif</td>
            <td><strong>{{ $i->patternRevision->pattern->code }}</strong> {{ $i->patternRevision->pattern->name }}
                @if ($i->carriedFrom)<div><span class="status" title="Left unstarted on {{ $i->carriedFrom->workList?->list_number }}">carried over from {{ $i->carriedFrom->workList?->list_date?->displayShortDate() }}</span></div>@endif</td>
            <td>{{ $i->productionOrder?->production_order_number ?? '—' }}<div class="muted small">{{ $i->productionOrder?->product?->name }}</div></td>
            <td>@if ($open)<input type="number" name="planned_moulds" form="row-{{ $i->id }}" min="1" max="5000" value="{{ $i->planned_moulds }}" style="width:80px" aria-label="Moulds for {{ $i->patternRevision->pattern->code }}">@else{{ $i->planned_moulds }}@endif</td>
            <td>{{ $made[$i->id] ?? 0 }}</td>
            <td style="white-space:nowrap">@if ($open)
                <form method="POST" action="{{ route('work-lists.items.update', [$list, $i]) }}" id="row-{{ $i->id }}" style="display:inline">@csrf @method('PUT')<button class="btn btn-small">Save</button></form>
                <form method="POST" action="{{ route('work-lists.items.remove', [$list, $i]) }}" style="display:inline">@csrf @method('DELETE')<button class="btn btn-small">Remove</button></form>
            @endif</td>
        </tr>
    @empty
        <tr><td colspan="6" class="muted">No rows. Add what to mould below.</td></tr>
    @endforelse
    </tbody></table></div>
    @if ($editable)
    <div class="panel-body">
        <form method="POST" action="{{ route('work-lists.items.add', $list) }}" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">@csrf
            <select name="pattern_revision_id" data-search-select required style="width:280px" aria-label="Pattern"><option value="">Add a pattern…</option>@foreach ($patterns as $p)<option value="{{ $p->id }}">{{ $p->pattern->code }} · {{ $p->pattern->name }}</option>@endforeach</select>
            <select name="moulding_station_id" required aria-label="Station">@foreach ($stations as $s)<option value="{{ $s->id }}">{{ $s->code }}</option>@endforeach</select>
            <input type="number" name="planned_moulds" min="1" max="5000" required placeholder="Moulds" style="width:90px" aria-label="Moulds">
            <button class="btn">Add row</button>
            <span class="muted small">The order is found from what still needs the pattern.</span>
        </form>
    </div>
    @endif
</div>
@endif
@endsection
