@extends('layouts.app')

@section('title', 'Pattern ownership')
@section('breadcrumb', 'Patterns / Ownership')

@section('content')
@php
    $charging = $rules['storage'] === 'CHARGE';
    $ownerName = fn ($row) => $row->owner_type === 'FOUNDRY' ? 'Foundry' : ($customers[$row->owner_customer_id]->name ?? 'Customer');
@endphp
<div class="page-heading">
    <div><h1>Pattern ownership</h1><p>Whose patterns the foundry holds, which are away, which are being paid off, and which have sat unused.</p></div>
    <div class="actions">@if (\App\Support\SettingsNav::canOpen())<a class="btn" href="{{ route('settings.pattern-ownership.edit') }}">Ownership rules</a>@endif<a class="btn" href="{{ route('patterns.index') }}">Pattern master</a></div>
</div>

<div class="tab-marker" data-tab-start="By owner"></div>
<div class="panel">
    <div class="panel-header"><h2>Patterns by owner</h2><span class="muted small">Archived, returned and scrapped patterns are not counted</span></div>
    <div class="table-wrap"><table>
        <thead><tr><th>Owner</th><th>Ownership</th><th>Patterns</th><th>Insured value{{ \App\Support\Formats::currencySuffix() }}</th><th></th></tr></thead>
        <tbody>
            @forelse ($byOwner->sortBy(fn ($r) => ($r->owner_type === 'FOUNDRY' ? '0' : '1').$ownerName($r)) as $row)
                <tr><td>{{ $ownerName($row) }}</td><td>{{ $row->owner_type === 'PAYING_OFF' ? 'Being paid off' : ($row->owner_type === 'FOUNDRY' ? 'Foundry' : 'Customer') }}</td><td>{{ \App\Support\Formats::number($row->n) }}</td><td>{{ $row->value !== null ? \App\Support\Formats::number((float) $row->value, 0) : '—' }}</td>
                    <td><a href="{{ route('patterns.index', ['owner' => $row->owner_customer_id ?? 'FOUNDRY']) }}">Patterns →</a></td></tr>
            @empty
                <tr><td colspan="5" class="muted" style="text-align:center;padding:24px">No patterns yet.</td></tr>
            @endforelse
        </tbody>
    </table></div>
</div>

@if ($payingOff->isNotEmpty())
<div class="panel">
    <div class="panel-header"><h2>Being paid off</h2><span class="muted small">{{ $rules['pay_off'] === 'AUTO' ? 'Ownership passes automatically when the castings are dispatched' : 'Ready ones need "Record as paid off" on the pattern' }}</span></div>
    <div class="table-wrap"><table>
        <thead><tr><th>Pattern</th><th>Customer</th><th>Castings dispatched</th><th></th></tr></thead>
        <tbody>
            @foreach ($payingOff as $row)
                <tr><td><a href="{{ route('patterns.show', $row['pattern']) }}#ownership">{{ $row['pattern']->code }}</a> <span class="muted small">{{ $row['pattern']->name }}</span></td><td>{{ $row['pattern']->ownerCustomer?->name }}</td>
                    <td class="mono">{{ \App\Support\Formats::number($row['dispatched']) }} / {{ \App\Support\Formats::number((int) $row['target']) }}</td><td>@if ($row['ready'])<span class="status status-completed">Paid off</span>@endif</td></tr>
            @endforeach
        </tbody>
    </table></div>
</div>
@endif

@if ($away->isNotEmpty())
<div class="panel">
    <div class="panel-header"><h2>Away from the store</h2><span class="muted small">At the pattern shop or lent out</span></div>
    <div class="table-wrap"><table>
        <thead><tr><th>Pattern</th><th>Owner</th><th>Where</th></tr></thead>
        <tbody>
            @foreach ($away as $p)
                <tr><td><a href="{{ route('patterns.show', $p) }}#ownership">{{ $p->code }}</a> <span class="muted small">{{ $p->name }}</span></td><td>{{ $p->owner_type === 'FOUNDRY' ? 'Foundry' : $p->ownerLabel() }}</td><td>{{ \App\Models\Pattern::CUSTODIES[$p->custody] }}{{ $p->custody_location ? ' · '.$p->custody_location : '' }}</td></tr>
            @endforeach
        </tbody>
    </table></div>
</div>
@endif

<div class="tab-marker" data-tab-start="Idle patterns"></div>
<div class="panel">
    <div class="panel-header"><h2>Idle patterns</h2><span class="muted small">Not moulded for {{ $rules['idle_months'] }} months or more: ask the customer to collect them or agree to scrap them</span></div>
    <div class="panel-body">
        <form method="GET" action="{{ route('pattern-ownership.index') }}" class="filters">
            <div class="field"><label for="customer">Customer</label><select id="customer" name="customer"><option value="">All owners</option>@foreach ($customers as $c)<option value="{{ $c->id }}" @selected($customerId === $c->id)>{{ $c->name }}</option>@endforeach</select></div>
            <button class="btn btn-primary" type="submit">Filter</button>
        </form>
    </div>
    @if ($idle->isEmpty())
        <div class="empty-state">No idle patterns.</div>
    @else
        <div class="table-wrap"><table>
            <thead><tr><th>Pattern</th><th>Owner</th><th>Last moulded</th><th>Idle</th>@if ($charging)<th>Storage due{{ \App\Support\Formats::currencySuffix() }}</th>@endif</tr></thead>
            <tbody>
                @foreach ($idle as $row)
                    <tr><td><a href="{{ route('patterns.show', $row['pattern']) }}#ownership">{{ $row['pattern']->code }}</a> <span class="muted small">{{ $row['pattern']->name }}</span></td>
                        <td>{{ $row['pattern']->owner_type === 'FOUNDRY' ? 'Foundry' : $row['pattern']->ownerLabel() }}</td>
                        <td>{{ $row['last_used']?->displayDate() ?? 'never' }}</td><td>{{ $row['months'] }} months</td>
                        @if ($charging)<td class="mono">{{ $row['charge'] !== null ? \App\Support\Formats::number($row['charge'], 0) : '—' }}</td>@endif</tr>
                @endforeach
            </tbody>
            @if ($charging)<tfoot><tr><td colspan="4"><strong>Total</strong></td><td class="mono"><strong>{{ \App\Support\Formats::number($idle->sum('charge'), 0) }}</strong></td></tr></tfoot>@endif
        </table></div>
    @endif
</div>

@endsection
