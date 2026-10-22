@extends('layouts.app')

@section('title', 'Moulds made and poured')
@section('breadcrumb', 'Quick flow / Moulds made and poured')

@php
    $label = fn ($o) => $o['order']->production_order_number.' · '.$o['order']->product?->code.' · '.$o['customer'].' · '.$o['ordered'].' pcs'.($o['due'] ? ', due '.$o['due']->displayShortDate() : '');
@endphp

@section('content')
@include('partials.search-select')
<div class="page-heading">
    <div><h1>Moulds made and poured</h1><p>Type in what the floor did. Each entry also writes the moulding run or pour record.</p></div>
    <div class="actions"><a class="btn" href="{{ route('quick.heat.index') }}">Next heat</a></div>
</div>

@include('quickflow::floor._grid', ['kind' => 'made', 'title' => 'Moulds made', 'action' => route('quick.floor.made'), 'submit' => 'Save moulds made'])
@include('quickflow::floor._grid', ['kind' => 'poured', 'title' => 'Moulds poured', 'action' => route('quick.floor.poured'), 'submit' => 'Save moulds poured'])

<div class="panel"><div class="panel-header"><h2>Heats in progress</h2></div>
    @forelse ($heats as $h)
        @php $t = $h->thermalTests->first(); @endphp
        <div class="panel-body" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;justify-content:space-between">
            <div><a href="{{ route('heats.show', $h) }}"><strong>{{ $h->heat_number }}</strong></a> · {{ $h->ironGrade?->code }} · {{ $h->furnace?->code }}
                <span class="muted small">{{ $t ? 'Last thermal test: '.strtolower(str_replace('_', ' ', $t->result)).' at '.$t->taken_at->format('H:i') : 'No thermal test yet' }}</span></div>
            <a class="btn" href="{{ route('thermal.create', ['heat_id' => $h->id]) }}">Record thermal test</a>
        </div>
    @empty
        <div class="panel-body muted">No heats yet. Start one from the next heat page.</div>
    @endforelse
</div>

<div class="panel"><div class="panel-header"><h2>Recent entries</h2></div>
    <div class="table-wrap"><table><thead><tr><th>Date</th><th></th><th>Pattern</th><th>Station</th><th>Order</th><th>Heat</th><th>Moulds</th><th>Pieces</th></tr></thead><tbody>
        @forelse ($recent as $e)<tr><td>{{ $e->entry_date->displayShortDate() }}</td><td>{{ $e->kind === 'MADE' ? 'Made' : 'Poured' }}</td><td>{{ $e->pattern?->name ?? '—' }}</td><td>{{ $e->station?->code ?? '—' }}</td><td>{{ $e->productionOrder?->production_order_number }} {{ $e->productionOrder?->product?->code }}</td><td>{{ $e->heat?->heat_number }}</td><td>{{ $e->moulds }}</td><td>{{ $e->pieces() }}</td></tr>
        @empty<tr><td colspan="8" class="muted">Nothing entered yet.</td></tr>@endforelse
    </tbody></table></div>
</div>
@endsection
