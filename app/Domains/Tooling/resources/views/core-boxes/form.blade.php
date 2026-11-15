@extends('layouts.app')

@php $isNew = ! $box->exists; @endphp
@section('title', $isNew ? 'New core box' : 'Edit '.$box->code)
@section('breadcrumb')<a href="{{ route('core-boxes.index') }}">Core boxes</a> / {{ $isNew ? 'New' : $box->code }} @endsection

@section('content')
<div class="page-heading">
    <div><h1>{{ $isNew ? 'New core box' : 'Edit '.$box->code }}</h1><p>A box with more than one cavity can make a different core in each.</p></div>
</div>
<form method="POST" action="{{ $isNew ? route('core-boxes.store') : route('core-boxes.update', $box) }}">
    @csrf @if (! $isNew) @method('PUT') @endif
    <div class="panel"><div class="panel-body"><div class="form-grid">
        <div class="field"><label for="name">Name *</label><input id="name" name="name" required maxlength="255" value="{{ old('name', $box->name) }}"></div>
        <div class="field"><label for="code">Code</label><input id="code" name="code" maxlength="40" value="{{ old('code', $box->code) }}"><div class="help">Leave empty to number it automatically.</div></div>
        <div class="field"><label for="core_definition_id">Core it makes *</label><select id="core_definition_id" name="core_definition_id" required data-search-select><option value="">— choose —</option>@foreach ($cores as $c)<option value="{{ $c->id }}" @selected(old('core_definition_id', $box->core_definition_id) === $c->id)>{{ $c->code }} · {{ $c->name }}</option>@endforeach</select><div class="help">The main core. New cavities start on it. <a href="{{ route('cores.create') }}">Add a core first</a> if it is not listed.</div></div>
        <div class="field"><label for="cavities">Cavities *</label><input id="cavities" type="number" name="cavities" required min="1" max="200" value="{{ old('cavities', $box->cavities) }}"></div>
        @php
            $count = max(1, (int) old('cavities', $box->cavities ?: 1));
            $picked = old('cavity_cores', $cavityCores);
            $cavityOptions = function (string $value) use ($cores) {
                $html = '<option value="">Same as main core</option>';
                foreach ($cores as $c) { $html .= '<option value="'.e($c->id).'"'.($value === $c->id ? ' selected' : '').'>'.e($c->code.' · '.$c->name).'</option>'; }

                return $html.'<option value="none"'.($value === 'none' ? ' selected' : '').'>Not in use</option>';
            };
        @endphp
        <div class="field field-full" data-cavity-box @if ($count < 2) hidden @endif>
            <label>Core in each cavity</label>
            <div class="help" style="margin:0 0 6px">Leave a cavity on the main core, pick another core for it, or set it Not in use when it is blocked off.</div>
            @error('cavity_cores')<div class="error">{{ $message }}</div>@enderror
            <div class="table-wrap"><table class="cb-cavities"><thead><tr><th style="width:90px">Cavity</th><th>Core</th></tr></thead>
                <tbody data-cavity-rows>
                @foreach (range(1, $count) as $n)
                    <tr data-cavity="{{ $n }}"><td>{{ $n }}</td><td><select name="cavity_cores[{{ $n }}]" aria-label="Core in cavity {{ $n }}">{!! $cavityOptions((string) ($picked[$n] ?? '')) !!}</select></td></tr>
                @endforeach
                </tbody></table></div>
            <template data-cavity-template><tr data-cavity="__N__"><td>__N__</td><td><select name="cavity_cores[__N__]" aria-label="Core in cavity __N__">{!! $cavityOptions('') !!}</select></td></tr></template>
        </div>
        <div class="field"><label for="revision">Revision *</label><input id="revision" name="revision" required maxlength="20" value="{{ old('revision', $box->revision) }}"></div>
        <div class="field"><label for="status">Status *</label><select id="status" name="status" required>@foreach (['ACTIVE', 'INACTIVE', 'RETIRED'] as $s)<option value="{{ $s }}" @selected(old('status', $box->status) === $s)>{{ $s }}</option>@endforeach</select></div>
        <div class="field"><label for="origin">Where it comes from *</label><select id="origin" name="origin" required>@foreach (\App\Models\CoreBox::ORIGINS as $k => $l)<option value="{{ $k }}" @selected(old('origin', $box->origin) === $k)>{{ $l }}</option>@endforeach</select></div>
        <div class="field" data-origin="MODIFIED_BOX"><label for="modified_from_core_box_id">Modified from core box</label><select id="modified_from_core_box_id" name="modified_from_core_box_id" data-search-select><option value="">— choose —</option>@foreach ($otherBoxes as $b)<option value="{{ $b->id }}" @selected(old('modified_from_core_box_id', $box->modified_from_core_box_id) === $b->id)>{{ $b->code }} · {{ $b->name }}</option>@endforeach</select></div>
        <div class="field" data-origin="MODIFIED_PATTERN"><label for="modified_from_pattern_id">Modified from pattern</label><select id="modified_from_pattern_id" name="modified_from_pattern_id" data-search-select><option value="">— choose —</option>@foreach ($patterns as $p)<option value="{{ $p->id }}" @selected(old('modified_from_pattern_id', $box->modified_from_pattern_id) === $p->id)>{{ $p->code }} · {{ $p->name }}</option>@endforeach</select></div>
        <div class="field"><label for="material">Material</label><input id="material" name="material" maxlength="50" value="{{ old('material', $box->material) }}"></div>
        <div class="field"><label for="nominal_core_weight_kg">Core weight (kg)</label><input id="nominal_core_weight_kg" type="number" name="nominal_core_weight_kg" min="0" step="any" value="{{ old('nominal_core_weight_kg', $box->nominal_core_weight_kg) }}"></div>
        <div class="field"><label for="commissioned_on">Commissioned on</label><input id="commissioned_on" type="date" name="commissioned_on" value="{{ old('commissioned_on', $box->commissioned_on?->format('Y-m-d')) }}"></div>
        <div class="field field-full"><label for="description">Description</label><textarea id="description" name="description" rows="2" maxlength="2000">{{ old('description', $box->description) }}</textarea></div>
        <div class="field field-full"><label for="notes">Notes</label><textarea id="notes" name="notes" rows="2" maxlength="4000">{{ old('notes', $box->notes) }}</textarea></div>
    </div></div></div>
    <div class="actions"><button class="btn btn-primary" type="submit">Save core box</button><a class="btn" href="{{ $isNew ? route('core-boxes.index') : route('core-boxes.show', $box) }}">Cancel</a></div>
</form>
<script>
    (function () {
        const origin = document.getElementById('origin');
        const sync = () => document.querySelectorAll('[data-origin]').forEach(el => { el.hidden = el.dataset.origin !== origin.value; });
        origin.addEventListener('change', sync); sync();

        // The cavity rows follow the Cavities box: rows are added on the main core and the highest ones dropped.
        const count = document.getElementById('cavities'), box = document.querySelector('[data-cavity-box]'),
            rows = box.querySelector('[data-cavity-rows]'), tpl = box.querySelector('[data-cavity-template]');
        count.addEventListener('input', () => {
            const n = Math.max(1, Math.min(200, parseInt(count.value, 10) || 1));
            while (rows.children.length < n) { rows.insertAdjacentHTML('beforeend', tpl.innerHTML.replace(/__N__/g, String(rows.children.length + 1))); }
            while (rows.children.length > n) { rows.lastElementChild.remove(); }
            box.hidden = n < 2;
        });
    })();
</script>
@include('partials.search-select')
@endsection
