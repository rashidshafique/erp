{{-- Add or edit a tax rule. $rule, $action, $method; uses $regions, $statuses, $inventoryClasses and $componentCodes from the page. --}}
@php $cond = $rule->conditions ?? []; @endphp
<form method="POST" action="{{ $action }}" style="margin-top:12px;white-space:normal">@csrf @if ($method !== 'POST') @method($method) @endif
<div class="form-grid">
    <div class="field"><label>Name</label><input name="name" required maxlength="150" value="{{ $rule->name }}"></div>
    <div class="field"><label>Order</label><input type="number" name="priority" min="0" max="9999" required value="{{ $rule->priority }}"><div class="help">Lower runs first; it decides the order taxes show in.</div></div>
    <div class="field"><label>Applies to</label><select name="side">@foreach (\App\Domains\Tax\Models\TaxRule::SIDES as $k => $l)<option value="{{ $k }}" @selected($rule->side === $k)>{{ $l }}</option>@endforeach</select></div>
    <div class="field"><label>Goods or service</label><select name="supply_type"><option value="">Either</option><option value="GOODS" @selected(($cond['supply_type'] ?? null) === 'GOODS')>Goods</option><option value="SERVICE" @selected(($cond['supply_type'] ?? null) === 'SERVICE')>Service</option></select></div>
    @if ($regions)
        <div class="field field-full"><label>Supplied in</label><div class="tax-checks">@foreach ($regions as $r => $name)<label><input type="checkbox" name="regions[]" value="{{ $r }}" @checked(in_array($r, $cond['regions'] ?? [], true))> {{ $name }}</label>@endforeach</div></div>
    @endif
    @if ($statuses)
        <div class="field field-full"><label>Customer or supplier is</label><div class="tax-checks">@foreach ($statuses as $s => $label)<label><input type="checkbox" name="party_status[]" value="{{ $s }}" @checked(in_array($s, $cond['party_status'] ?? [], true))> {{ $label }}</label>@endforeach</div></div>
    @endif
    <div class="field"><label>Withholding agent</label><select name="withholding_agent"><option value="">Either</option><option value="1" @selected(($cond['withholding_agent'] ?? null) === true)>Yes</option><option value="0" @selected(($cond['withholding_agent'] ?? null) === false)>No</option></select></div>
    <div class="field"><label>Code starts with</label><input name="code_prefix" maxlength="20" value="{{ $cond['code_prefix'] ?? '' }}" placeholder="e.g. 7325"><div class="help">The tariff or service code, for reduced or zero rates.</div></div>
    <div class="field field-full"><label>Inventory class</label><div class="tax-checks">@foreach ($inventoryClasses as $ic => $label)<label><input type="checkbox" name="inventory_classes[]" value="{{ $ic }}" @checked(in_array($ic, $cond['inventory_classes'] ?? [], true))> {{ $label }}</label>@endforeach</div></div>
    <div class="field field-full"><label>Adds these taxes *</label><div class="tax-checks">@forelse ($componentCodes as $c => $name)<label><input type="checkbox" name="components[]" value="{{ $c }}" @checked(in_array($c, $rule->components ?? [], true))> {{ $name }}</label>@empty<span class="muted">Load or add taxes first.</span>@endforelse</div></div>
    <div class="field"><label>From</label><input type="date" name="effective_from" value="{{ $rule->effective_from?->toDateString() }}"></div>
    <div class="field"><label>Until</label><input type="date" name="effective_to" value="{{ $rule->effective_to?->toDateString() }}"></div>
    <div class="field"><label><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked($rule->is_active)> On</label></div>
</div>
<button class="btn btn-primary">Save rule</button>
</form>
