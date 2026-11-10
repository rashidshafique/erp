@extends('layouts.app')
@section('title', $asset->exists ? 'Edit '.$asset->code : 'New asset')
@section('breadcrumb')<a href="{{ route('assets.index') }}">Fixed assets</a> / @if ($asset->exists)<a href="{{ route('assets.show', $asset) }}">{{ $asset->code }}</a> / Edit @else New @endif @endsection
@section('content')
@php
    $v = fn (string $f) => old($f, $asset->{$f});
    $classDefaults = $classes->mapWithKeys(fn ($c) => [$c->id => ['kind' => $c->kind, 'method' => $c->method, 'life' => $c->life_years, 'rate' => $c->rate_percent]]);
    $patternFills = $patterns->mapWithKeys(fn ($p) => [$p->id => $p->amortise_qty]);
@endphp
<div class="page-heading">
    <div><h1>{{ $asset->exists ? 'Edit '.$asset->code : 'New asset' }}</h1>
        <p>{{ $asset->exists ? 'Changes here post nothing.' : 'Add plant, buildings, vehicles or tools the foundry already has, with their cost and depreciation to date. Nothing is posted: the opening balances already hold them.' }}</p></div>
    <div class="actions"><a class="btn" href="{{ $asset->exists ? route('assets.show', $asset) : route('assets.index') }}">Cancel</a></div>
</div>
<form method="POST" action="{{ $asset->exists ? route('assets.update', $asset) : route('assets.store') }}"
      x-data="{ cls: @js($v('asset_class_id')), kind: @js($v('kind')), method: @js($v('method') ?: 'STRAIGHT_LINE'), status: @js($v('status')), life: @js($v('life_years')), rate: @js($v('rate_percent')), fills: @js($v('life_fills')),
                defaults: @js($classDefaults), patternFills: @js($patternFills),
                pickClass() { const d = this.defaults[this.cls]; if (! d) return; this.kind = d.kind; this.method = d.method; if (d.life) this.life = d.life; if (d.rate) this.rate = d.rate; },
                pickPattern(id) { if (this.patternFills[id]) this.fills = this.patternFills[id]; } }">
    @csrf @if ($asset->exists) @method('PUT') @endif
    <div class="panel"><div class="panel-header"><h2>Asset</h2></div><div class="panel-body"><div class="form-grid">
        <div class="field"><label for="asset_class_id">Class *</label><select id="asset_class_id" name="asset_class_id" required x-model="cls" @change="pickClass()"><option value="">Choose…</option>@foreach ($classes as $c)<option value="{{ $c->id }}">{{ $c->name }} · {{ $c->costAccount?->code }}</option>@endforeach</select>@error('asset_class_id')<span class="error">{{ $message }}</span>@enderror</div>
        <div class="field"><label for="name">Name *</label><input id="name" name="name" required maxlength="150" value="{{ $v('name') }}" placeholder="e.g. Induction furnace IF-1">@error('name')<span class="error">{{ $message }}</span>@enderror</div>
        <div class="field"><label for="kind">Kind *</label><select id="kind" name="kind" x-model="kind">@foreach (\App\Domains\Assets\Models\FixedAsset::KINDS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
        @unless ($asset->exists)
        <div class="field"><label for="status">Status *</label><select id="status" name="status" x-model="status"><option value="IN_USE">In use</option><option value="IN_PROGRESS">In progress (not yet in use)</option></select></div>
        @endunless
        <div class="field" x-show="{{ $asset->exists ? ($asset->status === 'IN_PROGRESS' ? 'false' : 'true') : "status === 'IN_USE'" }}"><label for="in_use_on">In use since</label><input id="in_use_on" type="date" name="in_use_on" value="{{ $v('in_use_on')?->toDateString() ?? old('in_use_on') }}">@error('in_use_on')<span class="error">{{ $message }}</span>@enderror</div>
        <div class="field"><label for="location">Location</label><input id="location" name="location" maxlength="150" value="{{ $v('location') }}" placeholder="e.g. Melt shop, pattern store PS-R1-06"></div>
        <div class="field" x-show="kind === 'PATTERN'"><label for="pattern_id">Pattern</label><select id="pattern_id" name="pattern_id" @change="pickPattern($event.target.value)"><option value="">None</option>@foreach ($patterns as $p)<option value="{{ $p->id }}" @selected($v('pattern_id') === $p->id)>{{ $p->code }} · {{ $p->name }}</option>@endforeach</select><div class="help">Our own patterns only; fills come from its moulding runs.</div></div>
        <div class="field" x-show="kind === 'CORE_BOX'"><label for="core_box_id">Core box</label><select id="core_box_id" name="core_box_id"><option value="">None</option>@foreach ($coreBoxes as $b)<option value="{{ $b->id }}" @selected($v('core_box_id') === $b->id)>{{ $b->code }} · {{ $b->name }}</option>@endforeach</select><div class="help">Fills come from its core lots.</div></div>
        <div class="field" x-show="kind !== 'PATTERN' && kind !== 'CORE_BOX'"><label for="equipment_id">Equipment record</label><select id="equipment_id" name="equipment_id"><option value="">None</option>@foreach ($equipment as $e)<option value="{{ $e->id }}" @selected($v('equipment_id') === $e->id)>{{ $e->code }} · {{ $e->name }}</option>@endforeach</select></div>
    </div></div></div>
    <div class="panel"><div class="panel-header"><h2>Value and depreciation</h2></div><div class="panel-body"><div class="form-grid">
        <div class="field"><label for="cost">Cost</label><input id="cost" type="number" step="0.01" min="0" name="cost" value="{{ $v('cost') }}" @disabled($costLocked)>@if ($costLocked)<div class="help">Cost is the sum of its bills and journals.</div>@endif</div>
        <div class="field"><label for="residual_value">Residual value</label><input id="residual_value" type="number" step="0.01" min="0" name="residual_value" value="{{ $v('residual_value') }}"></div>
        <div class="field"><label for="method">Method *</label><select id="method" name="method" x-model="method">@foreach (\App\Domains\Assets\Models\FixedAsset::METHODS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
        <div class="field" x-show="method === 'STRAIGHT_LINE'"><label for="life_years">Life in years</label><input id="life_years" type="number" step="0.5" min="0" name="life_years" x-model="life">@error('life_years')<span class="error">{{ $message }}</span>@enderror</div>
        <div class="field" x-show="method === 'REDUCING_BALANCE'"><label for="rate_percent">Rate, % a year</label><input id="rate_percent" type="number" step="0.5" min="0" name="rate_percent" x-model="rate">@error('rate_percent')<span class="error">{{ $message }}</span>@enderror</div>
        <div class="field" x-show="method === 'USAGE'"><label for="life_fills">Life in fills</label><input id="life_fills" type="number" min="1" name="life_fills" x-model="fills"><div class="help">Taken from the pattern record when it has one.</div>@error('life_fills')<span class="error">{{ $message }}</span>@enderror</div>
        <div class="field"><label for="opening_depreciation">Depreciation to date</label><input id="opening_depreciation" type="number" step="0.01" min="0" name="opening_depreciation" value="{{ $v('opening_depreciation') }}"><div class="help">What it had lost before it came into this register.</div>@error('opening_depreciation')<span class="error">{{ $message }}</span>@enderror</div>
        <div class="field" x-show="method === 'USAGE'"><label for="opening_fills">Fills to date</label><input id="opening_fills" type="number" min="0" name="opening_fills" value="{{ $v('opening_fills') }}"></div>
        <div class="field"><label for="tax_class">Tax class</label><input id="tax_class" name="tax_class" maxlength="60" value="{{ $v('tax_class') }}" placeholder="Set by your accountant"></div>
        <div class="field field-full"><label for="notes">Notes</label><textarea id="notes" name="notes" rows="2" maxlength="2000">{{ $v('notes') }}</textarea></div>
    </div></div></div>
    <div class="actions"><button class="btn btn-primary" type="submit">{{ $asset->exists ? 'Save asset' : 'Add asset' }}</button></div>
</form>
@endsection
