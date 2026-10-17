@extends('layouts.app')

@section('title', 'Pattern ownership')
@section('breadcrumb', 'Patterns / Ownership')

@section('content')
@php
    $charging = $rules['storage'] === 'CHARGE';
    $ownerName = fn ($row) => $row->owner_type === 'FOUNDRY' ? 'Foundry' : ($customers[$row->owner_customer_id]->name ?? 'Customer');
@endphp
<style>
    .po-rules { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 12px 20px; }
    .po-rules label { display: flex; gap: 8px; align-items: flex-start; font-weight: 400; font-size: 12.5px; margin: 3px 0; }
    .po-rules label input[type=radio] { width: auto; min-height: 0; margin-top: 2px; }
</style>
<div class="page-heading">
    <div><h1>Pattern ownership</h1><p>Whose patterns the foundry holds, which are away, which are being paid off, and which have sat unused.</p></div>
    <div class="actions"><a class="btn" href="{{ route('patterns.index') }}">Pattern master</a></div>
</div>

<div class="tab-marker" data-tab-start="By owner"></div>
<div class="panel">
    <div class="panel-header"><h2>Patterns by owner</h2><span class="muted small">Archived, returned and scrapped patterns are not counted</span></div>
    <div class="table-wrap"><table>
        <thead><tr><th>Owner</th><th>Ownership</th><th>Patterns</th><th>Insured value (PKR)</th><th></th></tr></thead>
        <tbody>
            @forelse ($byOwner->sortBy(fn ($r) => ($r->owner_type === 'FOUNDRY' ? '0' : '1').$ownerName($r)) as $row)
                <tr><td>{{ $ownerName($row) }}</td><td>{{ $row->owner_type === 'PAYING_OFF' ? 'Being paid off' : ($row->owner_type === 'FOUNDRY' ? 'Foundry' : 'Customer') }}</td><td>{{ number_format($row->n) }}</td><td>{{ $row->value !== null ? number_format((float) $row->value, 0) : '—' }}</td>
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
                    <td class="mono">{{ number_format($row['dispatched']) }} / {{ number_format((int) $row['target']) }}</td><td>@if ($row['ready'])<span class="status status-completed">Paid off</span>@endif</td></tr>
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
            <thead><tr><th>Pattern</th><th>Owner</th><th>Last moulded</th><th>Idle</th>@if ($charging)<th>Storage due (PKR)</th>@endif</tr></thead>
            <tbody>
                @foreach ($idle as $row)
                    <tr><td><a href="{{ route('patterns.show', $row['pattern']) }}#ownership">{{ $row['pattern']->code }}</a> <span class="muted small">{{ $row['pattern']->name }}</span></td>
                        <td>{{ $row['pattern']->owner_type === 'FOUNDRY' ? 'Foundry' : $row['pattern']->ownerLabel() }}</td>
                        <td>{{ $row['last_used']?->format('d M Y') ?? 'never' }}</td><td>{{ $row['months'] }} months</td>
                        @if ($charging)<td class="mono">{{ $row['charge'] !== null ? number_format($row['charge'], 0) : '—' }}</td>@endif</tr>
                @endforeach
            </tbody>
            @if ($charging)<tfoot><tr><td colspan="4"><strong>Total</strong></td><td class="mono"><strong>{{ number_format($idle->sum('charge'), 0) }}</strong></td></tr></tfoot>@endif
        </table></div>
    @endif
</div>

<div class="tab-marker" data-tab-start="Rules"></div>
<div class="panel">
    <div class="panel-header"><h2>Pattern ownership rules</h2><span class="muted small">Changing them needs the settings permission</span></div>
    <div class="panel-body">
        <form method="POST" action="{{ route('settings.pattern-ownership') }}">
            @csrf @method('PUT')
            <div class="po-rules">
                @foreach (\App\Support\PatternOwnershipRules::RULES as $key => [, $label, $options])
                    <fieldset style="border:0;padding:0;margin:0">
                        <legend style="font-weight:600;font-size:13px;margin-bottom:4px">{{ $label }}</legend>
                        @if ($options === null)
                            <input type="number" name="{{ $key }}" value="{{ $rules[$key] }}" min="0" step="1" style="max-width:140px" aria-label="{{ $label }}">
                        @else
                            @foreach ($options as $value => $text)<label><input type="radio" name="{{ $key }}" value="{{ $value }}" @checked($rules[$key] === $value)> {{ $text }}</label>@endforeach
                        @endif
                    </fieldset>
                @endforeach
            </div>
            <p class="muted small">Scrapping a customer's pattern always needs their written consent and an approval (Settings → Approvals). Returned and scrapped patterns can never be moulded.</p>
            <button class="btn btn-primary" type="submit">Save rules</button>
        </form>
    </div>
</div>
@endsection
