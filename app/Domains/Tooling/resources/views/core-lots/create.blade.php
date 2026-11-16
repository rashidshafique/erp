@extends('layouts.app')

@section('title', 'Cores made')
@php $stockOn = \App\Domains\Tooling\Support\CoreRules::stockOn(); @endphp
@section('breadcrumb')<a href="{{ $stockOn ? route('core-lots.index') : route('cores.index') }}">{{ $stockOn ? 'Core stock' : 'Cores' }}</a> / Cores made @endsection

@section('content')
<style>
    .cm { max-width: 560px; } .cm .form-grid { grid-template-columns: 1fr 1fr; } .cm .full { grid-column: 1 / -1; }
    .cm .used { grid-column: 1 / -1; display: grid; grid-template-columns: 1fr 120px; gap: 12px; padding: 12px; border: 1px solid var(--border); border-radius: 8px; background: #f8f9fa; }
    .cm-note { margin: 12px 0 0; color: #55585d; font-size: 12.5px; }
    @media (max-width: 600px) { .cm .form-grid, .cm .used { grid-template-columns: 1fr; } }
</style>
<div class="page-heading"><div><h1>Cores made</h1><p>One entry per core and box at the end of the day.</p></div></div>
<form method="POST" action="{{ route('core-lots.store') }}" class="cm">
    @csrf
    <div class="panel"><div class="panel-body"><div class="form-grid">
        <div class="field full"><label for="core_definition_id">Core *</label><select id="core_definition_id" name="core_definition_id" required data-search-select><option value="">Choose a core…</option>@foreach ($cores as $c)<option value="{{ $c->id }}" @selected(old('core_definition_id', $core?->id) === $c->id)>{{ $c->name }} · {{ $c->code }}</option>@endforeach</select></div>
        <div class="field full"><label for="core_box_id">Core box *</label><select id="core_box_id" name="core_box_id" required><option value="">Choose a core box…</option>
            @foreach ($boxes as $b)<option value="{{ $b->id }}" data-cores="{{ $b->cavityMap->pluck('core_definition_id')->unique()->implode(' ') }}" @selected(old('core_box_id') === $b->id)>{{ $b->code }} · {{ $b->name }}</option>@endforeach
        </select><div class="help" id="box-help">Only boxes that make the chosen core are offered.</div></div>
        <div class="field"><label for="quantity_made">Quantity *</label><input id="quantity_made" type="number" name="quantity_made" required min="1" max="100000" value="{{ old('quantity_made') }}"></div>
        <div class="field"><label for="made_on">Made on *</label><input id="made_on" type="date" name="made_on" required max="{{ today()->format('Y-m-d') }}" value="{{ old('made_on', today()->format('Y-m-d')) }}"></div>
        <div class="field full"><label class="check"><input type="checkbox" id="use_now" name="use_now" value="1" @checked(old('use_now', $run !== null))> Used today in a moulding run</label></div>
        <div class="used" data-when="used">
            <div class="field"><label for="moulding_run_id">Moulding run</label><select id="moulding_run_id" name="moulding_run_id"><option value="">— none —</option>@foreach ($runs as $r)<option value="{{ $r->id }}" @selected(old('moulding_run_id', $run?->id) === $r->id)>{{ $r->run_number }}@if ($r->patternRevision?->pattern) · {{ $r->patternRevision->pattern->name }}@endif</option>@endforeach</select></div>
            <div class="field"><label for="used_quantity">Used</label><input id="used_quantity" type="number" name="used_quantity" min="1" max="100000" value="{{ old('used_quantity') }}" placeholder="All"></div>
        </div>
        <div class="field full" data-when="kept"><label for="location"><span id="loc-label">Where they are kept</span> {{ $locationRequired ? '*' : '' }}</label><input id="location" name="location" maxlength="100" list="core-places" value="{{ old('location') }}" placeholder="Shelf, rack or bay"><datalist id="core-places">@foreach ($places as $p)<option value="{{ $p }}">@endforeach</datalist></div>
        <div class="field full"><label for="notes">Notes <span class="muted small">Optional</span></label><textarea id="notes" name="notes" rows="2" maxlength="2000">{{ old('notes') }}</textarea></div>
    </div>
    <p class="cm-note">Creates lot <span class="mono">{{ $nextLot }}</span><span id="left-note"></span>.</p>
    </div></div>
    <div class="actions"><a class="btn" href="{{ $run ? route('moulding-runs.show', $run) : ($stockOn ? route('core-lots.index') : route('cores.index')) }}">Cancel</a><button class="btn btn-primary" type="submit">Save</button></div>
</form>
<script>
    (function () {
        const $ = id => document.getElementById(id);
        const core = $('core_definition_id'), box = $('core_box_id'), now = $('use_now'), made = $('quantity_made'), used = $('used_quantity'), loc = $('location');
        const required = @js($locationRequired);
        function sync() {
            const fits = Array.from(box.options).filter(o => o.value && (!core.value || (o.dataset.cores || '').split(' ').includes(core.value)));
            Array.from(box.options).forEach(o => { if (o.value) { o.hidden = o.disabled = !fits.includes(o); } });
            if (box.selectedOptions[0] && box.selectedOptions[0].disabled) { box.value = ''; }
            if (core.value && fits.length === 1) { box.value = fits[0].value; }
            $('box-help').textContent = core.value && fits.length === 1 ? 'The only box that makes it.' : (core.value && !fits.length ? 'No core box in use makes this core.' : 'Only boxes that make the chosen core are offered.');
            const n = parseInt(made.value, 10) || 0, u = now.checked ? (parseInt(used.value, 10) || n) : 0, left = Math.max(0, n - u);
            document.querySelector('[data-when="used"]').hidden = !now.checked;
            document.querySelector('[data-when="kept"]').hidden = now.checked && n > 0 && left === 0;
            $('loc-label').textContent = now.checked ? 'Where the rest is kept' : 'Where they are kept';
            loc.required = required && !(now.checked && left === 0);
            used.max = n || 100000;
            $('left-note').textContent = n ? ' · ' + left + ' left' + (now.checked ? ' after the run' : '') : '';
        }
        [core, now].forEach(el => el.addEventListener('change', sync)); [made, used].forEach(el => el.addEventListener('input', sync)); sync();
    })();
</script>
@endsection
