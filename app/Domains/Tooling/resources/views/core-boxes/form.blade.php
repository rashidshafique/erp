@extends('layouts.app')

@php $isNew = ! $box->exists; @endphp
@section('title', $isNew ? 'New core box' : 'Edit '.$box->code)
@section('breadcrumb')<a href="{{ route('core-boxes.index') }}">Core boxes</a> / {{ $isNew ? 'New' : $box->code }} @endsection

@section('content')
{{-- Design 29f, layout B: the fields on the left and the cavities as they are typed on the right. --}}
<div class="page-heading">
    <div><h1>{{ $isNew ? 'New core box' : 'Edit '.$box->code }}</h1><p>List each core the box makes and how many cavities make it.</p></div>
</div>
@include('tooling::core-boxes._cavity-map-css')
<style>
    .cbf { display: grid; grid-template-columns: minmax(0, 1fr) 340px; gap: 20px; align-items: start; }
    .cbf-aside { position: sticky; top: 16px; }
    .cbf-own summary { cursor: pointer; list-style: none; } .cbf-own summary::-webkit-details-marker { display: none; }
    .cbf-own summary .lnk { color: var(--accent); margin-left: 6px; }
    .cbf-more > summary { cursor: pointer; font-weight: 600; margin: 4px 0 10px; }
    @media (max-width: 900px) { .cbf { grid-template-columns: 1fr; } .cbf-aside { position: static; } }
</style>
<form method="POST" action="{{ $isNew ? route('core-boxes.store') : route('core-boxes.update', $box) }}">
    @csrf @if (! $isNew) @method('PUT') @endif
    <div class="cbf">
    <div>
    <div class="panel"><div class="panel-body"><div class="form-grid">
        <div class="field"><label for="name">Name *</label><input id="name" name="name" required maxlength="255" value="{{ old('name', $box->name) }}"></div>
        <div class="field"><label for="revision">Revision *</label><input id="revision" name="revision" required maxlength="20" value="{{ old('revision', $box->revision) }}"></div>
        @php
            $rows = collect(old('box_cores', $boxCores))->values()->all() ?: [['core' => '', 'qty' => 1]];
            $blockedNow = (int) old('blocked', $blocked);
            $coreOptions = function (string $value) use ($cores) {
                $html = '<option value="">— choose —</option>';
                foreach ($cores as $c) { $html .= '<option value="'.e($c->id).'"'.($value === $c->id ? ' selected' : '').'>'.e($c->code.' · '.$c->name).'</option>'; }

                return $html;
            };
            $coreSelect = fn (string $i, string $value) => '<select id="bc-core-'.$i.'" name="box_cores['.$i.'][core]" data-search-select data-quick-list="cores" data-quick-add="qd-core" data-quick-add-label="New core…" aria-label="Core">'.$coreOptions($value).'</select>';
        @endphp
        <div class="field field-full" data-box-cores>
            <label>Cores this box makes *</label>
            @foreach (['box_cores', 'core_definition_id'] as $k)@error($k)<div class="error">{{ $message }}</div>@enderror @endforeach
            @foreach ($errors->get('box_cores.*') as $messages)<div class="error">{{ $messages[0] }}</div>@break @endforeach
            <div class="table-wrap"><table class="cb-cores"><thead><tr><th>Core</th><th style="width:110px">Cavities</th><th style="width:40px"><span class="sr-only">Remove</span></th></tr></thead>
                <tbody data-core-rows>
                @foreach ($rows as $i => $r)
                    <tr data-core-row><td>{!! $coreSelect((string) $i, (string) ($r['core'] ?? '')) !!}</td>
                        <td><input type="number" name="box_cores[{{ $i }}][qty]" min="1" max="200" required value="{{ $r['qty'] ?? 1 }}" aria-label="Cavities making this core" data-core-qty></td>
                        <td><button type="button" class="btn btn-small" data-core-remove aria-label="Remove core">&times;</button></td></tr>
                @endforeach
                </tbody></table></div>
            <template data-core-template><tr data-core-row><td>{!! $coreSelect('__I__', '') !!}</td>
                <td><input type="number" name="box_cores[__I__][qty]" min="1" max="200" required value="1" aria-label="Cavities making this core" data-core-qty></td>
                <td><button type="button" class="btn btn-small" data-core-remove aria-label="Remove core">&times;</button></td></tr></template>
            <div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap;margin-top:8px">
                <button type="button" class="btn btn-small" data-core-add>+ Add core</button>
                <label style="display:flex;align-items:center;gap:6px;font-weight:400">Blocked cavities <input type="number" name="blocked" min="0" max="199" value="{{ $blockedNow }}" style="width:80px" data-core-blocked></label>
            </div>
            <div class="help">The first core is the box's main core. Blocked cavities are plugged or not in use. Cavities are numbered in row order; change single cavities on the box page.</div>
        </div>
        <div class="field"><label for="origin">Where it comes from *</label><select id="origin" name="origin" required>@foreach (\App\Models\CoreBox::ORIGINS as $k => $l)<option value="{{ $k }}" @selected(old('origin', $box->origin) === $k)>{{ $l }}</option>@endforeach</select></div>
        <div class="field" data-origin="MODIFIED_BOX"><label for="modified_from_core_box_id">Source core box</label><select id="modified_from_core_box_id" name="modified_from_core_box_id" data-search-select><option value="">— choose —</option>@foreach ($otherBoxes as $b)<option value="{{ $b->id }}" @selected(old('modified_from_core_box_id', $box->modified_from_core_box_id) === $b->id)>{{ $b->code }} · {{ $b->name }}</option>@endforeach</select>@error('modified_from_core_box_id')<div class="error">{{ $message }}</div>@enderror</div>
        <div class="field" data-origin="MODIFIED_PATTERN"><label for="modified_from_pattern_id">Source pattern</label><select id="modified_from_pattern_id" name="modified_from_pattern_id" data-search-select><option value="">— choose —</option>@foreach ($patterns as $p)<option value="{{ $p->id }}" @selected(old('modified_from_pattern_id', $box->modified_from_pattern_id) === $p->id)>{{ $p->code }} · {{ $p->name }}</option>@endforeach</select>@error('modified_from_pattern_id')<div class="error">{{ $message }}</div>@enderror</div>
        <div class="field field-full">
            @php $ownErrors = $errors->hasAny(['owner_type', 'owner_customer_id', 'custody', 'custody_location', 'ownership_reference', 'return_notice_days']); @endphp
            <details class="cbf-own" @if ($ownErrors || old('owner_type', $box->owner_type) === 'CUSTOMER' && $isNew) open @endif>
                <summary>Ownership: {{ $box->ownerLabel() }} · {{ \App\Models\CoreBox::CUSTODY_LABELS[$box->custody] ?? $box->custody }}<span class="lnk">Change</span></summary>
                <div class="form-grid" style="margin-top:10px">
                    <div class="field"><label for="f-owner_type">Owner *</label><select id="f-owner_type" name="owner_type">@foreach (['FOUNDRY' => 'Foundry', 'CUSTOMER' => 'Customer'] as $k => $l)<option value="{{ $k }}" @selected(old('owner_type', $box->owner_type ?? 'FOUNDRY') === $k)>{{ $l }}</option>@endforeach</select></div>
                    <div class="field"><label for="f-owner_customer_id">Customer</label><select data-search-select id="f-owner_customer_id" name="owner_customer_id"><option value="">—</option>@foreach ($customers as $c)<option value="{{ $c->id }}" @selected(old('owner_customer_id', $box->owner_customer_id) === $c->id)>{{ $c->name }}</option>@endforeach</select>@error('owner_customer_id')<div class="error">{{ $message }}</div>@enderror</div>
                    <div class="field"><label for="f-ownership_reference">Ownership tag</label><input id="f-ownership_reference" name="ownership_reference" maxlength="100" value="{{ old('ownership_reference', $box->ownership_reference) }}" placeholder="Tag on the box, e.g. MAS-114"></div>
                    <div class="field"><label for="f-return_notice_days">Return on request, days' notice</label><input id="f-return_notice_days" type="number" name="return_notice_days" min="0" max="365" value="{{ old('return_notice_days', $box->return_notice_days) }}">@error('return_notice_days')<div class="error">{{ $message }}</div>@enderror</div>
                    <div class="field"><label for="f-custody">Where it is now *</label><select id="f-custody" name="custody">@foreach (\App\Models\CoreBox::CUSTODIES as $k)<option value="{{ $k }}" @selected(old('custody', $box->custody ?? 'IN_STORE') === $k)>{{ \App\Models\CoreBox::CUSTODY_LABELS[$k] }}</option>@endforeach</select></div>
                    <div class="field"><label for="f-custody_location">Location / with whom</label><input id="f-custody_location" name="custody_location" maxlength="150" value="{{ old('custody_location', $box->custody_location) }}" placeholder="Rack CS-R2-04, or who makes the cores with it">@error('custody_location')<div class="error">{{ $message }}</div>@enderror</div>
                </div>
                <div class="help">A customer-owned box is never a fixed asset. It is listed here and in the tooling register with its owner and where it is.</div>
            </details>
        </div>
        <div class="field field-full">
            @php $moreOpen = ! $isNew || $errors->hasAny(['code', 'status', 'material', 'nominal_core_weight_kg', 'commissioned_on', 'description', 'notes']); @endphp
            <details class="cbf-more" @if ($moreOpen) open @endif>
                <summary>More details</summary>
                <div class="form-grid">
                    <div class="field"><label for="code">Code</label><input id="code" name="code" maxlength="40" value="{{ old('code', $box->code) }}"><div class="help">Leave empty to number it automatically.</div>@error('code')<div class="error">{{ $message }}</div>@enderror</div>
                    @unless ($isNew)<div class="field"><label for="status">Status *</label><select id="status" name="status" required>@foreach (['ACTIVE' => 'In use', 'INACTIVE' => 'Inactive', 'RETIRED' => 'Retired'] as $s => $l)<option value="{{ $s }}" @selected(old('status', $box->status) === $s)>{{ $l }}</option>@endforeach</select></div>@endunless
                    <div class="field"><label for="material">Material</label><input id="material" name="material" maxlength="50" value="{{ old('material', $box->material) }}"></div>
                    <div class="field"><label for="nominal_core_weight_kg">Core weight (kg)</label><input id="nominal_core_weight_kg" type="number" name="nominal_core_weight_kg" min="0" step="any" value="{{ old('nominal_core_weight_kg', $box->nominal_core_weight_kg) }}"></div>
                    <div class="field"><label for="commissioned_on">Commissioned on</label><input id="commissioned_on" type="date" name="commissioned_on" value="{{ old('commissioned_on', $box->commissioned_on?->format('Y-m-d')) }}"></div>
                    <div class="field field-full"><label for="description">Description</label><textarea id="description" name="description" rows="2" maxlength="2000">{{ old('description', $box->description) }}</textarea></div>
                    <div class="field field-full"><label for="notes">Notes</label><textarea id="notes" name="notes" rows="2" maxlength="4000">{{ old('notes', $box->notes) }}</textarea></div>
                </div>
            </details>
        </div>
    </div></div></div>
    <div class="actions"><button class="btn btn-primary" type="submit">Save core box</button><a class="btn" href="{{ $isNew ? route('core-boxes.index') : route('core-boxes.show', $box) }}">Cancel</a></div>
    </div>
    <aside class="panel cbf-aside" aria-labelledby="cbf-preview-title"><div class="panel-header"><h2 id="cbf-preview-title">As you type it</h2></div>
        <div class="panel-body"><div class="cb-map small"><div class="cb-map-cells" data-preview-cells></div><p class="muted small" style="margin:0" data-preview-line aria-live="polite"></p></div>
            <p class="help" style="margin-top:10px">The total follows as cavities are typed. Save is blocked only if a core has 0 cavities.</p></div>
    </aside>
    </div>
</form>
@include('partials.quick-dialog', ['id' => 'qd-core', 'title' => 'New core', 'action' => route('cores.store'), 'submit' => 'Add core', 'fields' => 'tooling::core-boxes._quick-core-fields'])
<script>
    (function () {
        const origin = document.getElementById('origin');
        const sync = () => document.querySelectorAll('[data-origin]').forEach(el => { el.hidden = el.dataset.origin !== origin.value; });
        origin.addEventListener('change', sync); sync();

        // "Cores this box makes": add and remove rows, and draw the cavities as they would be numbered (row order, blocked last).
        const box = document.querySelector('[data-box-cores]'), rows = box.querySelector('[data-core-rows]'), tpl = box.querySelector('[data-core-template]');
        const cells = document.querySelector('[data-preview-cells]'), line = document.querySelector('[data-preview-line]');
        const tints = @json(\App\Models\CoreBox::MAP_TINTS);
        let next = rows.children.length;
        const cell = (n, text, tint) => {
            const d = document.createElement('div'); d.className = 'cb-cell' + (tint ? '' : ' blocked'); if (tint) d.style.background = tint;
            const a = document.createElement('span'); a.className = 'n'; a.textContent = n;
            const b = document.createElement('span'); b.className = 'c'; b.textContent = text;
            d.append(a, b); return d;
        };
        const draw = () => {
            const counts = new Map();
            rows.querySelectorAll('[data-core-row]').forEach(r => {
                const s = r.querySelector('select'), q = parseInt(r.querySelector('[data-core-qty]').value, 10) || 0;
                if (!s.value || q < 1) return;
                const name = s.options[s.selectedIndex].text.split(' · ').slice(1).join(' · ') || s.options[s.selectedIndex].text;
                counts.set(s.value, { name, qty: (counts.get(s.value)?.qty || 0) + q });
            });
            const blocked = Math.max(0, parseInt(box.querySelector('[data-core-blocked]').value, 10) || 0);
            cells.replaceChildren();
            let n = 0, i = 0;
            counts.forEach(c => {
                const tint = tints[i++ % tints.length], short = c.name.replace(/\s+core$/i, '');
                for (let k = 0; k < c.qty && n < 200; k++) cells.append(cell(++n, short, tint));
            });
            for (let k = 0; k < blocked && n < 200; k++) cells.append(cell(++n, 'Blocked', null));
            const parts = [...counts.values()].map(c => c.qty + ' × ' + c.name);
            if (blocked) parts.push(blocked + ' blocked');
            line.textContent = n ? n + (n === 1 ? ' cavity' : ' cavities') + ' · ' + parts.join(', ') : 'Choose a core to see the cavities.';
            rows.querySelectorAll('[data-core-remove]').forEach(b => { b.disabled = rows.children.length < 2; });
        };
        box.addEventListener('input', draw);
        box.addEventListener('change', draw);
        box.querySelector('[data-core-add]').addEventListener('click', () => { rows.insertAdjacentHTML('beforeend', tpl.innerHTML.replace(/__I__/g, String(next++))); draw(); });
        rows.addEventListener('click', e => { const b = e.target.closest('[data-core-remove]'); if (b && rows.children.length > 1) { b.closest('tr').remove(); draw(); } });
        draw();
    })();
</script>
@include('partials.search-select')
@endsection
