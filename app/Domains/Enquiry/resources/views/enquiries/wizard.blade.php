{{-- The new customer request, as one dialog over the enquiries list. The sections follow the enquiry intake of design 1b (customer, part,
     volumes, quality requirements) and the type boxes are laid out as the development dialog of design 12a. The type settles which
     questions are asked and what normally follows. Save draft keeps the enquiry as NEW; Send to feasibility opens it for review.
     Cancel, Escape or the cross go back to the list; a failed save returns here with the input kept and the errors shown. --}}
@extends('enquiry::enquiries.index')
@section('dialog')
@include('partials.quick-dialog-assets')
<style>
    dialog.wz-modal { width: min(820px, calc(100vw - 32px)); max-height: calc(100vh - 48px); overflow: visible; }
    dialog.wz-modal form { display: flex; flex-direction: column; max-height: calc(100vh - 48px); }
    .wz-modal .qd-head { padding: 16px 24px; } .wz-modal .qd-head h2 { font-size: 15px; font-weight: 600; }
    .wz-modal .qd-body { padding: 20px 24px; display: flex; flex-direction: column; gap: 22px; max-height: none; flex: 1 1 auto; min-height: 0; }
    .wz-modal .qd-foot { padding: 14px 24px; border-top: 1px solid var(--border); justify-content: space-between; align-items: center; }
    .wz-modal .qd-foot > div { display: flex; gap: 8px; }
    .wz-sec { display: flex; flex-direction: column; gap: 10px; }
    .wz-sec > h3 { margin: 0; font-size: 13px; font-weight: 600; }
    .wz-cards { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
    .wz-card { display: flex; gap: 10px; align-items: flex-start; border: 1px solid var(--border); border-radius: 8px; padding: 12px 14px; background: #fff; cursor: pointer; }
    .wz-card:hover { border-color: var(--accent); }
    .wz-card input { flex: none; margin: 2px 0 0; }
    .wz-card.sel { border-color: var(--accent); background: color-mix(in srgb, var(--accent) 7%, #fff); }
    .wz-card > div { min-width: 0; } .wz-card strong { display: block; margin-bottom: 2px; } .wz-card span.b { display: block; font-size: 12px; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .wz-card:has(input:focus-visible) { outline: 2px solid var(--accent); outline-offset: 1px; }
    .wz-custrow { display: flex; align-items: center; gap: 10px; }
    .wz-custrow select { flex: 1 1 auto; min-width: 0; } .wz-opt { display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; margin: 0; }
    .wz-chips { display: flex; flex-wrap: wrap; gap: 8px; }
    .wz-chip { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 999px; border: 1px solid var(--border); background: #fff; font-size: 13px; cursor: pointer; }
    .wz-chip.on { background: var(--accent); border-color: var(--accent); color: #fff; }
    .wz-chip input { position: absolute; opacity: 0; pointer-events: none; }
    .wz-chip:has(input:focus-visible) { outline: 2px solid var(--accent); outline-offset: 1px; }
    .wz-stages { display: flex; gap: 6px; }
    .wz-stages > div { flex: 1; display: flex; flex-direction: column; gap: 6px; font-size: 12px; } .wz-stages > div::before { content: ''; height: 4px; border-radius: 2px; background: var(--accent); }
    .wz-note { font-size: 12px; color: var(--muted); margin: 0; }
    .wz-modal .form-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; }
    .wz-modal .form-grid .field { grid-column: span 2; } .wz-modal .form-grid .field.w1 { grid-column: span 1; } .wz-modal .field-full { grid-column: 1 / -1 !important; }
    .wz-req > label:first-child::after, .wz-star::after { content: ' *'; color: #c0392b; font-weight: 600; }
    .wz-modal [hidden] { display: none !important; }
    input[type=checkbox], input[type=radio] { accent-color: var(--accent); }
    @media (max-width: 640px) { .wz-cards { grid-template-columns: 1fr; } .wz-modal .form-grid { grid-template-columns: 1fr; } .wz-modal .form-grid .field, .wz-modal .form-grid .field.w1 { grid-column: 1 / -1; } .wz-custrow { flex-wrap: wrap; } }
</style>
@php
    $o = fn ($k, $d = null) => old($k, $preset[$k] ?? $d);
    $scJs = collect($scenarios)->map(fn ($s) => ['needs' => $s['needs'], 'label' => $s['label'], 'blurb' => $s['blurb'], 'advice' => $s['advice'], 'cat' => $s['file_category']])->all();
    $presetScenario = old('source_type', $preset['scenario'] ?? null);
    $typeNow = old('type', match (true) { blank($presetScenario) => null, $presetScenario === 'REPEAT' => 'REPEAT', $presetScenario === 'CHANGE' => 'MODIFICATION', default => 'NEW_PART' });
    $giveNow = old('give', in_array($presetScenario, $types['NEW_PART']['gives'], true) ? $presetScenario : null);
    $presetCustomer = ($preset['customer'] ?? null) ? $customers->firstWhere('id', $preset['customer']) : null;
    $ticked = (array) old('quality', []);
    $productPhotos = \App\Models\Attachment::coverUrlsFor('product', $products->pluck('id')->all());
@endphp
<dialog class="qd wz-modal" id="wzDialog" aria-labelledby="wzTitle">
<form method="POST" action="{{ route('enquiries.wizard.store') }}" enctype="multipart/form-data" id="wz" novalidate data-no-save-bar>@csrf
    <div class="qd-head"><h2 id="wzTitle">New customer request</h2><button type="button" class="qd-x" data-close aria-label="Close">&times;</button></div>
    <div class="qd-body">
        <div class="alert alert-error qd-errors" role="alert" id="wzErrors" @unless ($errors->any()) hidden @endunless>@foreach ($errors->all() as $message)<div>{{ $message }}</div>@endforeach</div>
        <p class="wz-note">Fields marked <span class="wz-star"></span> are required. The questions change with the type of request.</p>

        <div class="wz-sec"><h3>Customer</h3>
            <div class="form-grid">
                <div class="field field-full wz-custrow">
                    <label class="wz-opt"><input type="radio" name="customer_mode" value="EXISTING" @checked(old('customer_mode', 'EXISTING') === 'EXISTING') aria-label="Existing customer"></label>
                    @include('partials.search-select')<select id="customer_id" name="customer_id" data-search-select aria-label="Existing customer"><option value="">— choose existing customer —</option>@foreach ($customers as $c)<option value="{{ $c->id }}" data-sub="{{ $c->code }}" data-contact="{{ $c->contact_name }}" @selected($o('customer') === $c->id || old('customer_id') === $c->id)>{{ $c->code }} — {{ $c->name }}</option>@endforeach</select>
                    <label class="wz-opt"><input type="radio" name="customer_mode" value="NEW" @checked(old('customer_mode') === 'NEW')> New customer</label>
                </div>
                @error('customer_id')<div class="field field-full"><div class="error-text">{{ $message }}</div></div>@enderror
                <div class="field wz-req" data-mode="NEW"><label for="new_name">Company name</label><input id="new_name" name="new_name" value="{{ old('new_name') }}">@error('new_name')<div class="error-text">{{ $message }}</div>@enderror<div class="help">A short customer code is made for you.</div></div>
                <div class="field" data-mode="NEW"><label>Contact person</label><input name="new_contact" value="{{ old('new_contact') }}"></div>
                <div class="field" data-mode="NEW"><label>Phone</label><input name="new_phone" value="{{ old('new_phone') }}"></div>
                <div class="field" data-mode="NEW"><label>Email</label><input type="email" name="new_email" value="{{ old('new_email') }}">@error('new_email')<div class="error-text">{{ $message }}</div>@enderror</div>
                <div class="field" data-mode="NEW"><label>Payment terms</label><input name="new_terms" value="{{ old('new_terms') }}" placeholder="e.g. 30 days"></div>
                <div class="field"><label>Customer RFQ ref</label><input name="customer_reference" value="{{ old('customer_reference') }}"></div>
                <div class="field"><label>Reply by</label><input type="date" name="due_on" value="{{ old('due_on') }}"></div>
                <div class="field field-full"><label>Contact for this request</label><input name="contact_name" value="{{ old('contact_name') }}" placeholder="Defaults to the customer's contact"></div>
            </div>
        </div>

        <div class="wz-sec"><h3>Type of request <span class="wz-star"></span></h3>
            <input type="hidden" name="source_type" id="wzSource" value="{{ old('source_type', $preset['scenario'] ?? '') }}">
            <div class="wz-cards">
            @foreach ($types as $k => $t)
                <label class="wz-card"><input type="radio" name="type" value="{{ $k }}" id="wzType{{ $k }}" @checked($typeNow === $k)><div><strong>{{ $t['name'] }}</strong><span class="b">{{ $t['sub'] }}</span></div></label>
            @endforeach
            </div>
            <div class="field" id="wzGive" hidden><label for="give" class="wz-star">What do they give us?</label><select id="give" name="give" data-search-select><option value="">— choose —</option>@foreach ($types['NEW_PART']['gives'] as $g)<option value="{{ $g }}" data-sub="{{ $scenarios[$g]['brief'] ?? '' }}" @selected($giveNow === $g)>{{ $scenarios[$g]['label'] }}</option>@endforeach</select></div>
            @error('source_type')<div class="error-text">{{ $message }}</div>@enderror
        </div>

        <div class="wz-sec" id="wzDetail" hidden><h3>Part</h3>
            <div class="form-grid">
                <div class="field field-full wz-req"><label>Description</label><input name="description" value="{{ old('description') }}" placeholder="e.g. Brake caliper bracket, front LH">@error('description')<div class="error-text">{{ $message }}</div>@enderror</div>
                <div class="field field-full wz-req" data-need="product"><label>Which of our parts?</label>
                    <select name="product_id" id="product_id" data-search-select="photos"><option value="">— choose —</option>@foreach ($products as $p)<option value="{{ $p->id }}" data-sub="{{ $p->code }}" @isset($productPhotos[$p->id]) data-img="{{ $productPhotos[$p->id] }}" @endisset data-name="{{ $p->name }}" data-weight="{{ $p->nominal_weight_kg }}" data-revs="{{ $p->revisions->map(fn ($r) => $r->revision.' ('.strtolower($r->status).')')->implode(', ') ?: 'no revisions yet' }}" @selected(old('product_id') === $p->id)>{{ $p->name }}</option>@endforeach</select>
                    <div class="help" id="prodInfo"></div>@error('product_id')<div class="error-text">{{ $message }}</div>@enderror</div>
                <div class="field field-full wz-req" data-need="change"><label>What has changed?</label><textarea name="change_note" rows="3" placeholder="New drawing revision, thicker wall, different grade…">{{ old('change_note') }}</textarea>@error('change_note')<div class="error-text">{{ $message }}</div>@enderror</div>
                <div class="field field-full wz-req" data-need="tool"><label>Tooling they will send</label><textarea name="tool_note" rows="3" placeholder="Pattern or core box, material, size, how many, condition, who repairs it…">{{ old('tool_note') }}</textarea>@error('tool_note')<div class="error-text">{{ $message }}</div>@enderror</div>
                <div class="field field-full wz-req" data-need="concept"><label>What does the part have to do?</label><textarea name="concept_note" rows="3" placeholder="Load, pressure, temperature, environment, mating parts, standards…">{{ old('concept_note') }}</textarea>@error('concept_note')<div class="error-text">{{ $message }}</div>@enderror</div>
                <div class="field field-full wz-req" data-need="transfer"><label>Where is it made today, and why move it?</label><textarea name="transfer_note" rows="3" placeholder="Current foundry, scrap or lead-time problems, price, pattern ownership…">{{ old('transfer_note') }}</textarea>@error('transfer_note')<div class="error-text">{{ $message }}</div>@enderror</div>
                <div class="field field-full wz-req" data-need="sample"><label>Describe the sample</label><textarea name="sample_note" rows="3" placeholder="How many, condition, whether it is cast or machined, what the customer knows about its material…">{{ old('sample_note') }}</textarea>@error('sample_note')<div class="error-text">{{ $message }}</div>@enderror</div>
                <div class="field w1" data-need="drawing"><label>Drawing no.</label><input name="drawing_ref" value="{{ old('drawing_ref') }}">@error('drawing_ref')<div class="error-text">{{ $message }}</div>@enderror</div>
                <div class="field w1" data-need="drawing"><label>Revision</label><input name="drawing_rev" value="{{ old('drawing_rev') }}"></div>
                <div class="field w1"><label for="material_text">Alloy spec</label>
                    <select name="material_text" id="material_text" data-search-select>
                        <option value="">— choose —</option>
                        @foreach ($grades as $g)<option value="{{ $g->code }}" data-sub="{{ $g->name }}" data-family="{{ $g->family }}" @selected(old('material_text') === $g->code)>{{ $g->code }}</option>@endforeach
                        <option value="__new__" data-sub="Not in the list" @selected(old('material_text') === '__new__')>+ Add new grade…</option>
                    </select>@error('material_text')<div class="error-text">{{ $message }}</div>@enderror</div>
                <div class="field w1" id="materialNewField" hidden><label for="material_new">New grade</label><input id="material_new" name="material_new" value="{{ old('material_new') }}" placeholder="e.g. EN-GJS-500-7" disabled>@error('material_new')<div class="error-text">{{ $message }}</div>@enderror</div>
                <div class="field w1"><label>Iron family</label><select name="family" data-search-select><option value="">— decide later —</option><option value="GREY" @selected(old('family') === 'GREY')>Grey iron</option><option value="DUCTILE" @selected(old('family') === 'DUCTILE')>Ductile iron</option></select></div>
                <div class="field w1"><label>Casting weight, kg</label><input type="number" step="any" min="0" name="weight_kg" value="{{ old('weight_kg') }}"></div>
                <div class="field w1"><label>Process</label><select name="process" data-search-select><option value="">— decide later —</option>@foreach (\App\Domains\Enquiry\Models\EnquiryItem::PROCESSES as $k => $l)<option value="{{ $k }}" @selected(old('process') === $k)>{{ $l }}</option>@endforeach</select></div>
                <div class="field w1"><label>Supply condition</label><select name="supply_condition" data-search-select><option value="">— not stated —</option>@foreach (\App\Domains\Enquiry\Models\EnquiryItem::SUPPLY_CONDITIONS as $k => $l)<option value="{{ $k }}" @selected(old('supply_condition') === $k)>{{ $l }}</option>@endforeach</select></div>
                <div class="field"><label style="display:inline-flex;gap:8px;align-items:center;margin-right:16px"><input type="checkbox" name="heat_treatment" value="1" @checked(old('heat_treatment'))> Heat treated</label><label style="display:inline-flex;gap:8px;align-items:center"><input type="checkbox" name="ndt" value="1" @checked(old('ndt'))> NDT required</label></div>
            </div>
        </div>

        <div class="wz-sec" id="wzVol" hidden><h3>Volumes</h3>
            <div class="form-grid">
                <div class="field w1"><label>Annual volume</label><input type="number" min="0" name="annual_qty" value="{{ old('annual_qty') }}"></div>
                <div class="field w1"><label>Batch / call-off</label><input name="call_off" maxlength="60" value="{{ old('call_off') }}" placeholder="e.g. 8,000 / month"></div>
                <div class="field w1"><label>SOP</label><input type="date" name="sop_on" value="{{ old('sop_on') }}"></div>
                <div class="field w1"><label>Programme life, years</label><input type="number" min="1" max="50" name="programme_years" value="{{ old('programme_years') }}"></div>
                <div class="field w1"><label>First batch</label><input type="number" min="0" name="first_batch_qty" value="{{ old('first_batch_qty') }}"><div class="help">The quantity the quotation prices.</div></div>
                <div class="field w1"><label>Target price each</label><input type="number" step="any" min="0" name="target_price" value="{{ old('target_price') }}"></div>
            </div>
        </div>

        <div class="wz-sec" id="wzQuality" hidden><h3>Quality requirements</h3>
            <div class="wz-chips">
            @foreach (\App\Domains\Enquiry\Models\EnquiryItem::QUALITY as $k => $l)
                <label class="wz-chip @if (in_array($k, $ticked, true)) on @endif"><input type="checkbox" name="quality[]" value="{{ $k }}" @checked(in_array($k, $ticked, true))>{{ $l }}</label>
            @endforeach
            </div>
            <div class="form-grid">
                <div class="field w1" id="ppapLevel" hidden><label for="ppap_level" class="wz-star">PPAP level</label><select id="ppap_level" name="ppap_level" disabled><option value="">— choose —</option>@foreach (range(1, 5) as $n)<option value="{{ $n }}" @selected((string) old('ppap_level') === (string) $n)>Level {{ $n }}</option>@endforeach</select></div>
                <div class="field field-full"><label>+ Customer-specific</label><input name="quality_other" maxlength="200" value="{{ old('quality_other') }}" placeholder="Anything else the customer's requirements ask for"></div>
            </div>
        </div>

        <div class="wz-sec" id="wzFiles" hidden><h3>Files</h3>
            <div class="form-grid">
                <div class="field field-full"><label>Drawing, model, photos or the customer's email</label><input type="file" name="files[]" multiple data-max-kb="{{ \App\Support\Uploads::effectiveMaxKb() }}"><div class="help" id="fileHint">Drawings, models (STL, STEP), photos or the customer's email.</div>@error('files')<div class="error-text">{{ $message }}</div>@enderror @error('files.*')<div class="error-text">{{ $message }}</div>@enderror</div>
                <div class="field"><label>Kind of file</label><select name="file_category" id="file_category" data-search-select>@foreach ($categories as $k => $l)<option value="{{ $k }}" @selected(old('file_category') === $k)>{{ $l }}</option>@endforeach</select></div>
            </div>
        </div>

        <div class="wz-sec" id="wzNext" hidden><h3>What happens next</h3>
            <div class="wz-stages" id="wzStages"></div>
        </div>
    </div>
    <div class="qd-foot"><span class="muted small">Opened from {{ $presetCustomer ? $presetCustomer->name : 'Enquiries' }}</span><div><a class="btn" href="{{ route('enquiries.index') }}">Cancel</a><button class="btn" type="submit" name="action" value="draft" id="wzDraft">Save draft</button><button class="btn btn-primary" type="submit" name="action" value="send" id="wzSubmit">Send to feasibility</button></div></div>
</form>
</dialog>
<script>
(function () {
    var SC = {!! json_encode($scJs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};
    var TY = {!! json_encode($types, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};
    var dlg = document.getElementById('wzDialog'), form = document.getElementById('wz');
    function val(n) { var e = form.elements[n]; return e ? (e.value || '').trim() : ''; }
    function checked(n) { var e = form.querySelector('[name="' + n + '"]:checked'); return e ? e.value : ''; }
    function scenario() { return document.getElementById('wzSource').value; }
    function mode() { return checked('customer_mode') || 'EXISTING'; }
    function toggle(g, on) { g.hidden = !on; g.querySelectorAll('input,select,textarea').forEach(function (i) { i.disabled = !on; }); }

    function applyMode() { form.querySelectorAll('[data-mode]').forEach(function (g) { toggle(g, g.getAttribute('data-mode') === mode()); }); }
    function applyType() {
        var k = checked('type'), t = TY[k];
        document.getElementById('wzSource').value = t ? (t.source || val('give')) : '';
        toggle(document.getElementById('wzGive'), !!(t && !t.source));
    }
    function applyQuality() {
        form.querySelectorAll('.wz-chip').forEach(function (c) { c.classList.toggle('on', c.querySelector('input').checked); });
        var ppap = form.querySelector('[name="quality[]"][value=PPAP]'), on = !!(ppap && ppap.checked && !document.getElementById('wzQuality').hidden);
        toggle(document.getElementById('ppapLevel'), on);
    }
    function applyScenario() {
        applyType();
        var s = SC[scenario()], needs = s ? s.needs : [];
        form.querySelectorAll('[data-need]').forEach(function (g) { toggle(g, needs.indexOf(g.getAttribute('data-need')) >= 0); });
        ['wzDetail', 'wzVol', 'wzQuality', 'wzFiles', 'wzNext'].forEach(function (id) { document.getElementById(id).hidden = !s; });
        form.querySelectorAll('.wz-card').forEach(function (c) { c.classList.toggle('sel', c.querySelector('input').checked); });
        var stages = document.getElementById('wzStages'); stages.innerHTML = '';
        (s ? s.advice : []).forEach(function (a) { var d = document.createElement('div'); d.textContent = a; stages.appendChild(d); });
        if (s && !form.dataset.catTouched) document.getElementById('file_category').value = s.cat;
        applyQuality();
    }
    function applyMaterial() {
        var sel = document.getElementById('material_text'), o = sel.options[sel.selectedIndex], isNew = sel.value === '__new__';
        var box = document.getElementById('materialNewField'); box.hidden = !isNew; box.querySelector('input').disabled = !isNew;
        if (isNew) { box.querySelector('input').focus(); }
        else if (o && o.dataset.family) { form.elements.family.value = (o.dataset.family === 'GREY' || o.dataset.family === 'DUCTILE') ? o.dataset.family : ''; }
    }
    document.getElementById('material_text').addEventListener('change', applyMaterial);
    function product() { var sel = document.getElementById('product_id'); return sel && sel.selectedIndex > 0 ? sel.options[sel.selectedIndex] : null; }
    document.getElementById('product_id').addEventListener('change', function () {
        var o = product(), info = document.getElementById('prodInfo');
        info.textContent = o ? 'Revisions: ' + o.dataset.revs + (o.dataset.weight ? ' · nominal weight ' + o.dataset.weight + ' kg' : '') : '';
        if (o && !val('description')) form.elements.description.value = o.dataset.name;
    });
    document.getElementById('customer_id').addEventListener('change', function () { if (this.value) { form.querySelector('[name=customer_mode][value=EXISTING]').checked = true; } });
    form.querySelectorAll('[name=type]').forEach(function (r) { r.addEventListener('change', function () { var t = TY[r.value]; if (t && t.existing && !val('new_name')) { form.querySelector('[name=customer_mode][value=EXISTING]').checked = true; } }); });
    document.getElementById('file_category').addEventListener('change', function () { form.dataset.catTouched = '1'; });
    form.addEventListener('change', function () { applyMode(); applyScenario(); });

    function problem() {
        if (mode() === 'EXISTING' && !val('customer_id')) return 'Choose the customer.';
        if (mode() === 'NEW' && !val('new_name')) return 'Enter the company name.';
        if (mode() === 'NEW' && val('new_email') && !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(val('new_email'))) return 'Enter a valid email address.';
        if (!checked('type')) return 'Choose the type of request.';
        if (!scenario()) return 'Choose what the customer gives us.';
        var n = SC[scenario()].needs;
        if (!val('description')) return 'Name the part.';
        if (val('material_text') === '__new__' && !val('material_new')) return 'Enter the new material grade.';
        if (n.indexOf('product') >= 0 && !val('product_id')) return 'Choose which of our parts it is.';
        var map = { change: ['change_note', 'Say what has changed.'], tool: ['tool_note', 'Describe the tooling.'], concept: ['concept_note', 'Say what the part has to do.'], transfer: ['transfer_note', 'Say where it is made today.'], sample: ['sample_note', 'Describe the sample.'] };
        for (var k in map) if (n.indexOf(k) >= 0 && !val(map[k][0])) return map[k][1];
        if (form.elements.ppap_level && !form.elements.ppap_level.disabled && !val('ppap_level')) return 'Choose the PPAP level.';
        if (val('weight_kg') && !(parseFloat(val('weight_kg')) >= 0)) return 'Weight must be a number, zero or more.';
        if (['first_batch_qty', 'annual_qty', 'target_price'].some(function (f) { return val(f) && !(parseFloat(val(f)) >= 0); })) return 'Quantities and price must be numbers, zero or more.';
        if (n.indexOf('drawing') >= 0 && scenario() !== 'CHANGE' && !val('drawing_ref') && !form.querySelector('input[type=file]').files.length) return 'Give the drawing number, or attach the drawing or model.';
        return '';
    }
    form.addEventListener('submit', function (e) {
        var p = problem(), box = document.getElementById('wzErrors');
        if (p) { e.preventDefault(); box.textContent = p; box.hidden = false; box.scrollIntoView({ block: 'nearest' }); return; }
        if (e.submitter && e.submitter.name) {
            var h = document.createElement('input'); h.type = 'hidden'; h.name = e.submitter.name; h.value = e.submitter.value; form.appendChild(h);
        }
        document.getElementById('wzSubmit').disabled = true; document.getElementById('wzDraft').disabled = true;
    });
    dlg.addEventListener('close', function () { window.location = '{{ route('enquiries.index') }}'; });
    applyMode(); applyScenario();
    (function () { var sel = document.getElementById('material_text'), box = document.getElementById('materialNewField'); if (sel.value === '__new__') { box.hidden = false; box.querySelector('input').disabled = false; } })();
    if (dlg.showModal) { dlg.showModal(); } else { dlg.setAttribute('open', ''); }
})();
</script>
@endsection
