@extends('layouts.app')

@php $isNew = ! $box->exists; @endphp
@section('title', $isNew ? 'New core box' : 'Edit '.$box->code)
@section('breadcrumb')<a href="{{ route('core-boxes.index') }}">Core boxes</a> / {{ $isNew ? 'New' : $box->code }} @endsection

@section('content')
<div class="page-heading">
    <div><h1>{{ $isNew ? 'New core box' : 'Edit '.$box->code }}</h1><p>List each core the box makes and how many cavities make it.</p></div>
</div>
<form method="POST" action="{{ $isNew ? route('core-boxes.store') : route('core-boxes.update', $box) }}">
    @csrf @if (! $isNew) @method('PUT') @endif
    <div class="panel"><div class="panel-body"><div class="form-grid">
        <div class="field"><label for="name">Name *</label><input id="name" name="name" required maxlength="255" value="{{ old('name', $box->name) }}"></div>
        <div class="field"><label for="code">Code</label><input id="code" name="code" maxlength="40" value="{{ old('code', $box->code) }}"><div class="help">Leave empty to number it automatically.</div></div>
        @php
            $rows = collect(old('box_cores', $boxCores))->values()->all() ?: [['core' => '', 'qty' => 1]];
            $blockedNow = (int) old('blocked', $blocked);
            $coreOptions = function (string $value) use ($cores) {
                $html = '<option value="">— choose —</option>';
                foreach ($cores as $c) { $html .= '<option value="'.e($c->id).'"'.($value === $c->id ? ' selected' : '').'>'.e($c->code.' · '.$c->name).'</option>'; }

                return $html;
            };
        @endphp
        <div class="field field-full" data-box-cores>
            <label>Cores this box makes *</label>
            @foreach (['box_cores', 'core_definition_id'] as $k)@error($k)<div class="error">{{ $message }}</div>@enderror @endforeach
            @foreach ($errors->get('box_cores.*') as $messages)<div class="error">{{ $messages[0] }}</div>@break @endforeach
            <div class="table-wrap"><table class="cb-cores"><thead><tr><th>Core</th><th style="width:110px">Cavities</th><th style="width:40px"><span class="sr-only">Remove</span></th></tr></thead>
                <tbody data-core-rows>
                @foreach ($rows as $i => $r)
                    <tr data-core-row><td><select name="box_cores[{{ $i }}][core]" data-search-select aria-label="Core">{!! $coreOptions((string) ($r['core'] ?? '')) !!}</select></td>
                        <td><input type="number" name="box_cores[{{ $i }}][qty]" min="1" max="200" required value="{{ $r['qty'] ?? 1 }}" aria-label="Cavities making this core" data-core-qty></td>
                        <td><button type="button" class="btn btn-small" data-core-remove aria-label="Remove core">&times;</button></td></tr>
                @endforeach
                </tbody></table></div>
            <template data-core-template><tr data-core-row><td><select name="box_cores[__I__][core]" data-search-select aria-label="Core">{!! $coreOptions('') !!}</select></td>
                <td><input type="number" name="box_cores[__I__][qty]" min="1" max="200" required value="1" aria-label="Cavities making this core" data-core-qty></td>
                <td><button type="button" class="btn btn-small" data-core-remove aria-label="Remove core">&times;</button></td></tr></template>
            <div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap;margin-top:8px">
                <button type="button" class="btn btn-small" data-core-add>+ Add core</button>
                <label style="display:flex;align-items:center;gap:6px;font-weight:400">Blocked cavities <input type="number" name="blocked" min="0" max="199" value="{{ $blockedNow }}" style="width:80px" data-core-blocked></label>
                <span class="muted">Cavities: <strong data-core-total>{{ collect($rows)->sum(fn ($r) => (int) ($r['qty'] ?? 0)) + $blockedNow }}</strong> (total)</span>
            </div>
            <div class="help">The first core is the box's main core. Blocked cavities are plugged or not in use.</div>
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

        // "Cores this box makes": add and remove rows, and keep the cavity total in step.
        const box = document.querySelector('[data-box-cores]'), rows = box.querySelector('[data-core-rows]'), tpl = box.querySelector('[data-core-template]');
        let next = rows.children.length;
        const total = () => {
            let n = parseInt(box.querySelector('[data-core-blocked]').value, 10) || 0;
            rows.querySelectorAll('[data-core-qty]').forEach(q => { n += parseInt(q.value, 10) || 0; });
            box.querySelector('[data-core-total]').textContent = n;
            rows.querySelectorAll('[data-core-remove]').forEach(b => { b.disabled = rows.children.length < 2; });
        };
        box.addEventListener('input', total);
        box.querySelector('[data-core-add]').addEventListener('click', () => { rows.insertAdjacentHTML('beforeend', tpl.innerHTML.replace(/__I__/g, String(next++))); total(); });
        rows.addEventListener('click', e => { const b = e.target.closest('[data-core-remove]'); if (b && rows.children.length > 1) { b.closest('tr').remove(); total(); } });
        total();
    })();
</script>
@include('partials.search-select')
@endsection
