@extends('layouts.app')

@section('title', 'Cores made')
@section('breadcrumb')<a href="{{ route('core-lots.index') }}">Core stock</a> / Cores made @endsection

@section('content')
<div class="page-heading"><div><h1>Cores made</h1><p>Record a batch of one core made in one core box. Tick "used straight away" when the cores go into moulds the same day.</p></div></div>
<form method="POST" action="{{ route('core-lots.store') }}">
    @csrf
    <div class="panel"><div class="panel-body"><div class="form-grid">
        <div class="field"><label for="core_definition_id">Core *</label><select id="core_definition_id" name="core_definition_id" required data-search-select><option value="">Choose a core…</option>@foreach ($cores as $c)<option value="{{ $c->id }}" @selected(old('core_definition_id', $core?->id) === $c->id)>{{ $c->code }} · {{ $c->name }}</option>@endforeach</select></div>
        <div class="field"><label for="core_box_id">Core box *</label><select id="core_box_id" name="core_box_id" required><option value="">Choose a core box…</option>
            @foreach ($boxes as $b)<option value="{{ $b->id }}" data-cores="{{ $b->cavityMap->pluck('core_definition_id')->unique()->implode(' ') }}" @selected(old('core_box_id') === $b->id)>{{ $b->code }} · {{ $b->name }} ({{ $b->cavities }} {{ $b->cavities === 1 ? 'cavity' : 'cavities' }})</option>@endforeach
        </select><div class="help">Only boxes with a cavity for the chosen core are listed.</div></div>
        <div class="field"><label for="quantity_made">Cores made *</label><input id="quantity_made" type="number" name="quantity_made" required min="1" max="100000" value="{{ old('quantity_made') }}"></div>
        <div class="field"><label for="made_on">Made on *</label><input id="made_on" type="date" name="made_on" required max="{{ today()->format('Y-m-d') }}" value="{{ old('made_on', today()->format('Y-m-d')) }}"></div>
        <div class="field"><label class="check" style="margin-top:22px"><input type="checkbox" id="use_now" name="use_now" value="1" @checked(old('use_now'))> Used straight away</label></div>
        <div class="field" data-when="used"><label for="moulding_run_id">Moulding run <span class="muted small">(optional)</span></label><select id="moulding_run_id" name="moulding_run_id"><option value="">— none —</option>@foreach ($runs as $r)<option value="{{ $r->id }}" @selected(old('moulding_run_id') === $r->id)>{{ $r->run_number }} · {{ $r->run_date?->displayDate() }}</option>@endforeach</select></div>
        <div class="field" data-when="kept"><label for="location">Where they are kept {{ $locationRequired ? '*' : '' }}</label><input id="location" name="location" maxlength="100" value="{{ old('location') }}" @required($locationRequired)><div class="help">Shelf, rack or bay. {{ $locationRequired ? 'Required by the core rules.' : 'Leave empty if you do not track it.' }}</div></div>
        <div class="field field-full"><label for="notes">Notes</label><textarea id="notes" name="notes" rows="2" maxlength="2000">{{ old('notes') }}</textarea></div>
    </div></div></div>
    <div class="actions"><button class="btn btn-primary" type="submit">Record cores</button><a class="btn" href="{{ route('core-lots.index') }}">Cancel</a></div>
</form>
<script>
    (function () {
        const core = document.getElementById('core_definition_id'), box = document.getElementById('core_box_id'), now = document.getElementById('use_now');
        function sync() {
            Array.from(box.options).forEach(o => { if (o.value) { o.hidden = o.disabled = !!core.value && !(o.dataset.cores || '').split(' ').includes(core.value); } });
            if (box.selectedOptions[0] && box.selectedOptions[0].disabled) { box.value = ''; }
            document.querySelectorAll('[data-when]').forEach(el => { el.hidden = (el.dataset.when === 'used') !== now.checked; });
        }
        core.addEventListener('change', sync); now.addEventListener('change', sync); sync();
    })();
</script>
@include('partials.search-select')
@endsection
