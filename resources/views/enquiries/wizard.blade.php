@extends('layouts.app')
@section('title', 'New request')
@section('breadcrumb')<a href="{{ route('enquiries.index') }}">Enquiries</a> / New request @endsection
@section('content')
<style>
    .wz-steps { display: flex; gap: 6px; flex-wrap: wrap; margin: 4px 0 14px; padding: 0; list-style: none; }
    .wz-steps li { padding: 5px 12px; border-radius: 999px; border: 1px solid var(--border); background: #fff; font-size: 12.5px; color: var(--muted); }
    .wz-steps li.on { background: var(--accent); border-color: var(--accent); color: #fff; font-weight: 600; }
    .wz-steps li.done { color: var(--accent); border-color: var(--accent); cursor: pointer; }
    .wz-step { display: none; } .wz-step.on { display: block; }
    .wz-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 10px; }
    .wz-card { display: block; border: 1px solid var(--border); border-radius: 10px; padding: 10px 12px; background: #fff; cursor: pointer; }
    .wz-card:hover { border-color: var(--accent); }
    .wz-card input { position: absolute; opacity: 0; pointer-events: none; }
    .wz-card.sel { border-color: var(--accent); box-shadow: 0 0 0 2px color-mix(in srgb, var(--accent) 22%, transparent); }
    .wz-card strong { display: block; margin-bottom: 3px; } .wz-card span { font-size: 12.5px; color: var(--muted); }
    .wz-hint { background: color-mix(in srgb, var(--accent) 8%, #fff); border: 1px solid color-mix(in srgb, var(--accent) 30%, #fff); border-radius: 8px; padding: 8px 12px; margin: 0 0 12px; font-size: 13px; }
    .wz-nav { display: flex; gap: 8px; margin-top: 14px; } .wz-review dt { font-weight: 600; margin-top: 8px; } .wz-review dd { margin: 0; }
    input[type=checkbox], input[type=radio] { accent-color: var(--accent); }
</style>
<div class="page-heading"><div><h1>New customer request</h1><p>Answer a few questions and the system opens the enquiry with the right information for this kind of request.</p></div></div>
<ol class="wz-steps" id="wzSteps"><li>1 Customer</li><li>2 Kind of request</li><li>3 The part</li><li>4 Quantities and files</li><li>5 Review</li></ol>
@php
    $o = fn ($k, $d = null) => old($k, $preset[$k] ?? $d);
    $scJs = collect($scenarios)->map(function ($s) { return ['needs' => $s['needs'], 'label' => $s['label'], 'blurb' => $s['blurb'], 'advice' => $s['advice'], 'cat' => $s['file_category']]; })->all();
@endphp
<form method="POST" action="{{ route('enquiries.wizard.store') }}" enctype="multipart/form-data" id="wz" novalidate>@csrf

<div class="wz-step panel" data-step="0"><div class="panel-header"><h2>Who is asking?</h2></div><div class="panel-body">
    <div class="form-grid">
        <div class="field field-full"><label style="display:inline-flex;gap:6px;margin-right:16px"><input type="radio" name="customer_mode" value="EXISTING" @checked(old('customer_mode', 'EXISTING') === 'EXISTING')> Existing customer</label><label style="display:inline-flex;gap:6px"><input type="radio" name="customer_mode" value="NEW" @checked(old('customer_mode') === 'NEW')> New customer</label></div>
        <div class="field field-full" data-mode="EXISTING"><label for="customer_id">Customer</label><select id="customer_id" name="customer_id"><option value="">— choose —</option>@foreach ($customers as $c)<option value="{{ $c->id }}" data-contact="{{ $c->contact_name }}" @selected($o('customer') === $c->id || old('customer_id') === $c->id)>{{ $c->code }} — {{ $c->name }}</option>@endforeach</select>@error('customer_id')<div class="error-text">{{ $message }}</div>@enderror</div>
        <div class="field" data-mode="NEW"><label for="new_name">Company name</label><input id="new_name" name="new_name" value="{{ old('new_name') }}">@error('new_name')<div class="error-text">{{ $message }}</div>@enderror<div class="help">A short customer code is made for you.</div></div>
        <div class="field" data-mode="NEW"><label>Contact person</label><input name="new_contact" value="{{ old('new_contact') }}"></div>
        <div class="field" data-mode="NEW"><label>Phone</label><input name="new_phone" value="{{ old('new_phone') }}"></div>
        <div class="field" data-mode="NEW"><label>Email</label><input type="email" name="new_email" value="{{ old('new_email') }}">@error('new_email')<div class="error-text">{{ $message }}</div>@enderror</div>
        <div class="field" data-mode="NEW"><label>Payment terms</label><input name="new_terms" value="{{ old('new_terms') }}" placeholder="e.g. 30 days"></div>
    </div>
</div></div>

<div class="wz-step panel" data-step="1"><div class="panel-header"><h2>What are they giving us?</h2></div><div class="panel-body">
    <div class="wz-cards">
    @foreach ($scenarios as $k => $s)
        <label class="wz-card"><input type="radio" name="source_type" value="{{ $k }}" @checked(old('source_type', $preset['scenario'] ?? null) === $k)><strong>{{ $s['label'] }}</strong><span>{{ $s['blurb'] }}</span></label>
    @endforeach
    </div>
    @error('source_type')<div class="error-text">{{ $message }}</div>@enderror
</div></div>

<div class="wz-step panel" data-step="2"><div class="panel-header"><h2>The part</h2></div><div class="panel-body">
    <div class="wz-hint" id="wzHint"></div>
    <div class="form-grid">
        <div class="field field-full"><label>What is the part called?</label><input name="description" value="{{ old('description') }}" placeholder="e.g. Pump housing">@error('description')<div class="error-text">{{ $message }}</div>@enderror</div>
        <div class="field field-full" data-need="product"><label>Which of our parts?</label>
            <select name="product_id" id="product_id"><option value="">— choose —</option>@foreach ($products as $p)<option value="{{ $p->id }}" data-name="{{ $p->name }}" data-weight="{{ $p->nominal_weight_kg }}" data-revs="{{ $p->revisions->map(fn ($r) => $r->revision.' ('.strtolower($r->status).')')->implode(', ') ?: 'no revisions yet' }}" @selected(old('product_id') === $p->id)>{{ $p->code }} — {{ $p->name }}</option>@endforeach</select>
            <div class="help" id="prodInfo"></div>@error('product_id')<div class="error-text">{{ $message }}</div>@enderror</div>
        <div class="field field-full" data-need="change"><label>What has changed?</label><textarea name="change_note" rows="3" placeholder="New drawing revision, thicker wall, different grade…">{{ old('change_note') }}</textarea>@error('change_note')<div class="error-text">{{ $message }}</div>@enderror</div>
        <div class="field field-full" data-need="tool"><label>Tooling they will send</label><textarea name="tool_note" rows="3" placeholder="Pattern or core box, material, size, how many, condition, who repairs it…">{{ old('tool_note') }}</textarea>@error('tool_note')<div class="error-text">{{ $message }}</div>@enderror</div>
        <div class="field field-full" data-need="concept"><label>What does the part have to do?</label><textarea name="concept_note" rows="3" placeholder="Load, pressure, temperature, environment, mating parts, standards…">{{ old('concept_note') }}</textarea>@error('concept_note')<div class="error-text">{{ $message }}</div>@enderror</div>
        <div class="field field-full" data-need="transfer"><label>Where is it made today, and why move it?</label><textarea name="transfer_note" rows="3" placeholder="Current foundry, scrap or lead-time problems, price, pattern ownership…">{{ old('transfer_note') }}</textarea>@error('transfer_note')<div class="error-text">{{ $message }}</div>@enderror</div>
        <div class="field field-full" data-need="sample"><label>Describe the sample</label><textarea name="sample_note" rows="3" placeholder="How many, condition, whether it is cast or machined, what the customer knows about its material…">{{ old('sample_note') }}</textarea>@error('sample_note')<div class="error-text">{{ $message }}</div>@enderror</div>
        <div class="field" data-need="drawing"><label>Drawing number</label><input name="drawing_ref" value="{{ old('drawing_ref') }}">@error('drawing_ref')<div class="error-text">{{ $message }}</div>@enderror</div>
        <div class="field" data-need="drawing"><label>Drawing revision</label><input name="drawing_rev" value="{{ old('drawing_rev') }}"></div>
        <div class="field"><label>Material asked for</label><input name="material_text" value="{{ old('material_text') }}" placeholder="e.g. EN-GJS-500-7"></div>
        <div class="field"><label>Iron family</label><select name="family"><option value="">— decide later —</option><option value="GREY" @selected(old('family') === 'GREY')>Grey iron</option><option value="DUCTILE" @selected(old('family') === 'DUCTILE')>Ductile iron</option></select></div>
        <div class="field"><label>Weight, kg (if known)</label><input type="number" step="0.001" min="0" name="weight_kg" value="{{ old('weight_kg') }}"><div class="help">Left blank, our own product weight is used for repeats. A casting analysis replaces it later.</div></div>
        <div class="field"><label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="heat_treatment" value="1" @checked(old('heat_treatment'))> Heat treated</label><label style="display:flex;gap:8px;align-items:center;margin-top:6px"><input type="checkbox" name="ndt" value="1" @checked(old('ndt'))> NDT required</label></div>
    </div>
</div></div>

<div class="wz-step panel" data-step="3"><div class="panel-header"><h2>Quantities, dates and files</h2></div><div class="panel-body">
    <div class="form-grid">
        <div class="field"><label>First batch</label><input type="number" min="0" name="first_batch_qty" value="{{ old('first_batch_qty') }}"><div class="help">The quantity the quotation will price.</div></div>
        <div class="field"><label>Yearly quantity</label><input type="number" min="0" name="annual_qty" value="{{ old('annual_qty') }}"></div>
        <div class="field"><label>Target price each</label><input type="number" step="0.01" min="0" name="target_price" value="{{ old('target_price') }}"></div>
        <div class="field"><label>Quote needed by</label><input type="date" name="due_on" value="{{ old('due_on') }}"></div>
        <div class="field"><label>Their reference</label><input name="customer_reference" value="{{ old('customer_reference') }}"></div>
        <div class="field"><label>Contact for this request</label><input name="contact_name" value="{{ old('contact_name') }}" placeholder="Defaults to the customer's contact"></div>
        <div class="field field-full"><label>Files</label><input type="file" name="files[]" multiple><div class="help" id="fileHint">Drawings, models (STL, STEP), photos or the customer's email.</div>@error('files')<div class="error-text">{{ $message }}</div>@enderror @error('files.*')<div class="error-text">{{ $message }}</div>@enderror</div>
        <div class="field"><label>Kind of file</label><select name="file_category" id="file_category">@foreach ($categories as $k => $l)<option value="{{ $k }}" @selected(old('file_category') === $k)>{{ $l }}</option>@endforeach</select></div>
    </div>
</div></div>

<div class="wz-step panel" data-step="4"><div class="panel-header"><h2>Check and create</h2></div><div class="panel-body">
    <dl class="wz-review" id="wzReview"></dl>
    <div class="wz-hint" id="wzNext"></div>
    <button class="btn btn-primary" type="submit" id="wzSubmit">Create the request</button>
</div></div>

<div class="wz-nav"><button type="button" class="btn" id="wzBack">Back</button><button type="button" class="btn btn-primary" id="wzNextBtn">Next</button></div>
</form>
<script>
(function () {
    var SC = {!! json_encode($scJs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};
    var form = document.getElementById('wz'), steps = [].slice.call(form.querySelectorAll('.wz-step')), pills = [].slice.call(document.querySelectorAll('#wzSteps li'));
    var cur = 0;
    function val(n) { var e = form.elements[n]; return e ? (e.value || '').trim() : ''; }
    function checked(n) { var e = form.querySelector('[name="' + n + '"]:checked'); return e ? e.value : ''; }
    function scenario() { return checked('source_type'); }
    function mode() { return checked('customer_mode') || 'EXISTING'; }

    function applyMode() { form.querySelectorAll('[data-mode]').forEach(function (g) { var on = g.getAttribute('data-mode') === mode(); g.hidden = !on; g.querySelectorAll('input,select,textarea').forEach(function (i) { i.disabled = !on; }); }); }
    function applyScenario() {
        var s = SC[scenario()], needs = s ? s.needs : [];
        form.querySelectorAll('[data-need]').forEach(function (g) { var on = needs.indexOf(g.getAttribute('data-need')) >= 0; g.hidden = !on; g.querySelectorAll('input,select,textarea').forEach(function (i) { i.disabled = !on; }); });
        document.getElementById('wzHint').innerHTML = s ? '<strong>' + s.label + '.</strong> ' + s.blurb : 'Choose the kind of request first.';
        form.querySelectorAll('.wz-card').forEach(function (c) { c.classList.toggle('sel', c.querySelector('input').checked); });
        if (s && !form.dataset.catTouched) document.getElementById('file_category').value = s.cat;
    }
    function product() { var sel = document.getElementById('product_id'); return sel && sel.selectedIndex > 0 ? sel.options[sel.selectedIndex] : null; }
    document.getElementById('product_id').addEventListener('change', function () {
        var o = product(), info = document.getElementById('prodInfo');
        info.textContent = o ? 'Revisions: ' + o.dataset.revs + (o.dataset.weight ? ' · nominal weight ' + o.dataset.weight + ' kg' : '') : '';
        if (o && !val('description')) form.elements.description.value = o.dataset.name;
    });
    document.getElementById('file_category').addEventListener('change', function () { form.dataset.catTouched = '1'; });
    form.addEventListener('change', function () { applyMode(); applyScenario(); });

    function problem(i) {
        if (i === 0) { if (mode() === 'EXISTING' && !val('customer_id')) return 'Choose the customer.'; if (mode() === 'NEW' && !val('new_name')) return 'Enter the company name.'; }
        if (i === 1 && !scenario()) return 'Choose what the customer is giving us.';
        if (i === 2) {
            var n = SC[scenario()].needs;
            if (!val('description')) return 'Name the part.';
            if (n.indexOf('product') >= 0 && !val('product_id')) return 'Choose which of our parts it is.';
            var map = { change: ['change_note', 'Say what has changed.'], tool: ['tool_note', 'Describe the tooling.'], concept: ['concept_note', 'Say what the part has to do.'], transfer: ['transfer_note', 'Say where it is made today.'], sample: ['sample_note', 'Describe the sample.'] };
            for (var k in map) if (n.indexOf(k) >= 0 && !val(map[k][0])) return map[k][1];
        }
        if (i === 3) {
            var n2 = SC[scenario()].needs, files = form.querySelector('input[type=file]').files.length;
            if (n2.indexOf('drawing') >= 0 && scenario() !== 'CHANGE' && !val('drawing_ref') && !files) return 'Give the drawing number on the previous step, or attach the drawing or model here.';
        }
        return '';
    }
    function review() {
        var s = SC[scenario()], dl = document.getElementById('wzReview'), rows = [];
        var cust = mode() === 'NEW' ? val('new_name') + ' (new customer)' : (form.elements.customer_id.selectedOptions[0] || {}).text;
        rows.push(['Customer', cust]); rows.push(['Request', s.label]); rows.push(['Part', val('description') + (product() ? ' — ' + product().text : '')]);
        ['drawing_ref:Drawing', 'material_text:Material', 'weight_kg:Weight kg', 'first_batch_qty:First batch', 'annual_qty:Yearly', 'target_price:Target price', 'due_on:Quote needed by', 'change_note:What changed', 'tool_note:Tooling', 'concept_note:Purpose', 'transfer_note:Transfer', 'sample_note:Sample'].forEach(function (p) {
            var a = p.split(':'), e = form.elements[a[0]]; if (e && !e.disabled && val(a[0])) rows.push([a[1], val(a[0])]);
        });
        var f = form.querySelector('input[type=file]').files.length; if (f) rows.push(['Files', f + ' to attach']);
        dl.innerHTML = ''; rows.forEach(function (r) { var dt = document.createElement('dt'), dd = document.createElement('dd'); dt.textContent = r[0]; dd.textContent = r[1]; dl.appendChild(dt); dl.appendChild(dd); });
        document.getElementById('wzNext').innerHTML = '<strong>What happens next</strong><ul style="margin:6px 0 0 18px">' + s.advice.map(function (a) { var li = document.createElement('li'); li.textContent = a; return li.outerHTML; }).join('') + '</ul>';
    }
    function show(i) {
        cur = i; steps.forEach(function (s, k) { s.classList.toggle('on', k === i); });
        pills.forEach(function (p, k) { p.className = k === i ? 'on' : (k < i ? 'done' : ''); });
        document.getElementById('wzBack').style.visibility = i === 0 ? 'hidden' : 'visible';
        document.getElementById('wzNextBtn').style.display = i === steps.length - 1 ? 'none' : '';
        if (i === 4) review();
        window.scrollTo(0, 0);
    }
    function go(i) {
        if (i > cur) { for (var k = cur; k < i; k++) { var p = problem(k); if (p) { alert(p); show(k); return; } } }
        show(i);
    }
    document.getElementById('wzNextBtn').onclick = function () { go(cur + 1); };
    document.getElementById('wzBack').onclick = function () { go(cur - 1); };
    pills.forEach(function (p, k) { p.onclick = function () { if (k < cur) go(k); }; });
    form.addEventListener('submit', function () { document.getElementById('wzSubmit').disabled = true; });
    applyMode(); applyScenario();
    var firstErr = form.querySelector('.error-text'), start = 0;
    if (firstErr) start = steps.indexOf(firstErr.closest('.wz-step'));
    else if ('{{ $preset['customer'] ?? '' }}' && '{{ $preset['scenario'] ?? '' }}') start = 2;
    else if ('{{ $preset['customer'] ?? '' }}') start = 1;
    show(Math.max(0, start));
})();
</script>
@endsection
