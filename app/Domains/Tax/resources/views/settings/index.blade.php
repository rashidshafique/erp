@extends('layouts.app')
@section('title', 'Taxation')
@section('content')
@php
    $pct = fn ($r) => rtrim(rtrim(number_format((float) $r, 3), '0'), '.').'%';
    $regions = $pack?->regions() ?? [];
    $statuses = $pack?->partyStatuses() ?? [];
@endphp
<style>
    .tax-cond { display: flex; flex-wrap: wrap; gap: 4px; }
    .tax-cond span { border: 1px solid var(--border); border-radius: 6px; padding: 1px 7px; font-size: 12px; color: var(--muted); white-space: nowrap; }
    .tax-checks { display: flex; flex-wrap: wrap; gap: 4px 14px; }
    .tax-checks label { display: inline-flex; gap: 5px; align-items: center; margin: 0; font-weight: 400; }
    .tax-rule-form summary { cursor: pointer; }
</style>
<div class="page-heading"><div><h1>Taxation</h1><p>The country whose tax rules apply, your registrations, the tax rates by date and the rules that put them on each line.</p></div></div>

@if ($errors->any())<div class="alert alert-error">{{ $errors->first() }}</div>@endif

<form method="POST" action="{{ route('tax.company.update') }}">@csrf @method('PUT')
<div class="panel"><div class="panel-header"><h2>Country and registration</h2></div><div class="panel-body"><div class="form-grid">
    <div class="field"><label for="jurisdiction">Country</label>
        <select id="jurisdiction" name="jurisdiction">@foreach ($countries as $c => $name)<option value="{{ $c }}" @selected($code === $c)>{{ $name }}</option>@endforeach</select>
        <div class="help">Each country is a pack of authorities, taxes and rules. More countries are added as packs.</div></div>
    @if ($regions)
        <div class="field"><label for="region">Your region</label>
            <select id="region" name="region"><option value="">—</option>@foreach ($regions as $r => $name)<option value="{{ $r }}" @selected(($company['region'] ?? null) === $r)>{{ $name }}</option>@endforeach</select>
            <div class="help">Where your services are supplied when a document does not say.</div></div>
    @endif
    @foreach ($pack?->registrationFields() ?? [] as $key => $label)
        <div class="field"><label for="reg-{{ $key }}">{{ $label }}</label><input id="reg-{{ $key }}" name="registrations[{{ $key }}]" maxlength="60" value="{{ $company['registrations'][$key] ?? '' }}"></div>
    @endforeach
    @if ($authorities->isNotEmpty())
        <div class="field field-full"><label>Registered with</label>
            <div class="tax-checks">@foreach ($authorities as $a)<label><input type="checkbox" name="registered[]" value="{{ $a->id }}" @checked($a->is_registered)> {{ $a->name }} <span class="muted small">({{ $a->code }})</span></label>@endforeach</div>
            <div class="help">You charge an authority's tax only once registered with it. Its registers and returns are produced separately.</div></div>
    @endif
</div></div><div class="panel-body"><button class="btn btn-primary">Save</button></div></div>
</form>

<form method="POST" action="{{ route('tax.checks.update') }}">@csrf @method('PUT')
<div class="panel"><div class="panel-header"><h2>Checks before an invoice is issued</h2></div><div class="panel-body"><div class="form-grid">
    @foreach (\App\Domains\Tax\Services\TaxRules::RULES as $rule => [, $label])
        <div class="field"><label for="check-{{ $rule }}">{{ $label }}</label>
            <select id="check-{{ $rule }}" name="checks[{{ $rule }}]">@foreach (\App\Domains\Tax\Services\TaxRules::LEVELS as $k => $l)<option value="{{ $k }}" @selected(\App\Domains\Tax\Services\TaxRules::get($rule) === $k)>{{ $l }}</option>@endforeach</select></div>
    @endforeach
</div></div><div class="panel-body"><button class="btn btn-primary">Save checks</button></div></div>
</form>

@if ($pack)
<div class="panel"><div class="panel-header"><h2>Starting taxes and rules</h2></div><div class="panel-body">
    <p class="muted">Loads {{ $pack->name() }}'s authorities, taxes and rules as a starting set. The rates are a starting point only: check each one against the current law before you invoice.</p>
    <form method="POST" action="{{ route('tax.defaults') }}" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">@csrf
        @if ($current->isNotEmpty() || $rules->isNotEmpty())<label style="margin:0"><input type="checkbox" name="replace" value="1"> Replace the taxes and rules below</label>@endif
        <button class="btn">Load {{ $pack->name() }} defaults</button>
    </form>
</div></div>
@endif

<div class="panel"><div class="panel-header"><h2>Tax rates in force</h2><span class="muted small">A new rate starts from a date; earlier documents keep the rate they were made at.</span></div>
<div class="table-wrap"><table><thead><tr><th>Tax</th><th>Authority</th><th>Kind</th><th>Rate</th><th>On</th><th>From</th><th>New rate from a date</th></tr></thead><tbody>
@forelse ($current as $c)
    <tr>
        <td><strong>{{ $c->name }}</strong><div class="muted small mono">{{ $c->code }}</div>@if ($c->note)<div class="muted small">{{ $c->note }}</div>@endif</td>
        <td>{{ $c->authority->code }}</td>
        <td>{{ \App\Domains\Tax\Models\TaxComponent::KINDS[$c->kind] }}</td>
        <td><strong>{{ $pct($c->rate) }}</strong></td>
        <td class="small">{{ $c->base === 'NET' ? 'Value before tax' : ($c->base === 'GROSS' ? 'Value with tax' : ($componentCodes[$c->base] ?? $c->base)) }}</td>
        <td class="small">{{ $c->effective_from->displayDate() }}</td>
        <td><form method="POST" action="{{ route('tax.components.rate', $c) }}" style="display:flex;gap:6px;flex-wrap:wrap">@csrf
            <input type="number" name="rate" step="0.001" min="0" max="100" required placeholder="%" style="width:80px" aria-label="New rate for {{ $c->name }}">
            <input type="date" name="effective_from" required aria-label="From">
            <button class="btn btn-small">Set</button></form></td>
    </tr>
@empty
    <tr><td colspan="7" class="muted">No taxes yet. Load the starting set above.</td></tr>
@endforelse
</tbody></table></div>
@if ($scheduled->isNotEmpty())
    <div class="panel-body"><strong>Coming in:</strong> @foreach ($scheduled as $s){{ $s->name }} {{ $pct($s->rate) }} from {{ $s->effective_from->displayDate() }}@if (! $loop->last) · @endif @endforeach</div>
@endif
</div>

<div class="panel"><div class="panel-header"><h2>Rules</h2><span class="muted small">Every rule whose conditions hold adds its taxes. A condition left blank matches anything.</span></div>
<div class="table-wrap"><table><thead><tr><th>Order</th><th>Rule</th><th>Applies to</th><th>When</th><th>Adds</th><th></th></tr></thead><tbody>
@forelse ($rules as $rule)
    @php $cond = $rule->conditions ?? []; @endphp
    <tr @class(['muted' => ! $rule->is_active])>
        <td>{{ $rule->priority }}</td>
        <td><strong>{{ $rule->name }}</strong>@unless ($rule->is_active)<div class="small">Off</div>@endunless</td>
        <td class="small">{{ \App\Domains\Tax\Models\TaxRule::SIDES[$rule->side] }}</td>
        <td><div class="tax-cond">
            @isset($cond['supply_type'])<span>{{ ucfirst(strtolower($cond['supply_type'])) }}</span>@endisset
            @foreach ($cond['regions'] ?? [] as $r)<span>in {{ $regions[$r] ?? $r }}</span>@endforeach
            @foreach ($cond['party_status'] ?? [] as $s)<span>{{ $statuses[$s] ?? $s }}</span>@endforeach
            @isset($cond['withholding_agent'])<span>{{ $cond['withholding_agent'] ? 'Withholding agent' : 'Not a withholding agent' }}</span>@endisset
            @foreach ($cond['inventory_classes'] ?? [] as $ic)<span>{{ $inventoryClasses[$ic] ?? $ic }}</span>@endforeach
            @isset($cond['code_prefix'])<span>Code starts {{ $cond['code_prefix'] }}</span>@endisset
            @if ($cond === [])<span>Always</span>@endif
        </div></td>
        <td class="small">{{ collect($rule->components)->map(fn ($c) => $componentCodes[$c] ?? $c)->implode(', ') }}</td>
        <td style="white-space:nowrap">
            <details class="tax-rule-form"><summary class="btn btn-small">Edit</summary>
                @include('tax::settings._rule-form', ['rule' => $rule, 'action' => route('tax.rules.update', $rule), 'method' => 'PUT'])
            </details>
            <form method="POST" action="{{ route('tax.rules.destroy', $rule) }}" style="display:inline">@csrf @method('DELETE')
                @include('partials.icon-button', ['type' => 'delete', 'name' => $rule->name, 'confirm' => "Remove the rule {$rule->name}?"])</form>
        </td>
    </tr>
@empty
    <tr><td colspan="6" class="muted">No rules yet.</td></tr>
@endforelse
</tbody></table></div>
<div class="panel-body"><details class="tax-rule-form"><summary class="btn">+ Add rule</summary>
    @include('tax::settings._rule-form', ['rule' => new \App\Domains\Tax\Models\TaxRule(['priority' => 100, 'side' => 'BOTH', 'is_active' => true, 'conditions' => [], 'components' => []]), 'action' => route('tax.rules.store'), 'method' => 'POST'])
</details></div>
</div>
@endsection
