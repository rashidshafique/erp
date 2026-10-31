{{-- Start development (design 12a), as a dialog over the list: pick the type first, and it sets where the record comes from, what is copied
     in and which stages it runs. Only the fields the chosen type needs are shown. Cancel, Escape or the cross go back to the list. --}}
@extends('development::index')
@section('dialog')
@include('partials.quick-dialog-assets')
@include('partials.search-select')
<style>
    dialog.dv-modal { width: min(760px, calc(100vw - 32px)); max-height: calc(100vh - 48px); overflow: visible; }
    dialog.dv-modal form { display: flex; flex-direction: column; max-height: calc(100vh - 48px); }
    .dv-modal .qd-head { padding: 16px 24px; } .dv-modal .qd-head h2 { font-size: 15px; font-weight: 600; }
    .dv-modal .qd-body { padding: 20px 24px; display: flex; flex-direction: column; gap: 20px; max-height: none; flex: 1 1 auto; min-height: 0; }
    .dv-modal .qd-foot { padding: 14px 24px; border-top: 1px solid var(--border); justify-content: space-between; align-items: center; }
    .dv-modal .qd-foot > div { display: flex; gap: 8px; }
    .dv-cards { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
    .dv-card { display: flex; gap: 10px; align-items: flex-start; border: 1px solid var(--border); border-radius: 8px; padding: 12px 14px; background: #fff; cursor: pointer; }
    .dv-card:hover { border-color: var(--accent); } .dv-card input { flex: none; margin: 2px 0 0; }
    .dv-card.sel { border-color: var(--accent); background: color-mix(in srgb, var(--accent) 7%, #fff); }
    .dv-card > div { min-width: 0; } .dv-card strong { display: block; margin-bottom: 2px; } .dv-card span.b { display: block; font-size: 12px; color: var(--muted); }
    .dv-sec { display: flex; flex-direction: column; gap: 8px; }
    .dv-sec > h3 { margin: 0; font-size: 12px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .06em; }
    .dv-chips { display: flex; flex-wrap: wrap; gap: 6px; } .dv-chip { font-size: 12px; padding: 4px 10px; border-radius: 999px; background: #f0f1f3; }
    .dv-stages { display: flex; gap: 6px; } .dv-stages > div { flex: 1; display: flex; flex-direction: column; gap: 6px; font-size: 12px; } .dv-stages > div::before { content: ''; height: 4px; border-radius: 2px; background: var(--accent); }
    .dv-note { font-size: 12px; color: var(--muted); }
    .dv-modal .form-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; } .dv-modal .field-full { grid-column: 1 / -1; }
    .dv-tool { display: inline-flex; gap: 6px; align-items: center; font-size: 12px; padding: 4px 10px; border-radius: 999px; background: #f0f1f3; } .dv-tool button, .dv-addtool { border: 0; background: none; cursor: pointer; color: var(--accent); font-size: 12px; padding: 0; }
    .dv-toolrow { display: grid; grid-template-columns: 76px 90px minmax(0, 1fr) 130px 20px; gap: 6px; align-items: center; font-size: 12px; } .dv-toolrow button { border: 0; background: none; cursor: pointer; color: var(--muted); }
    .dv-modal [hidden] { display: none !important; }
    @media (max-width: 640px) { .dv-cards, .dv-modal .form-grid { grid-template-columns: 1fr; } .dv-stages { flex-wrap: wrap; } }
</style>
@php
    $old = fn ($k) => old($k, $preset[$k] ?? null);
    $typeNow = old('type', $preset['type']);
    $coreOn = app(\App\Domains\Development\Services\ToolRegister::class)->toolingOn();
    $lifeOf = fn ($id) => isset($life[$id]) ? ' · '.$life[$id].'% life' : '';
@endphp
<dialog class="qd dv-modal" id="dvDialog" aria-labelledby="dvTitle">
<form method="POST" action="{{ route('developments.store') }}" id="dv" novalidate>@csrf
    <input type="hidden" name="opened_from" value="{{ $openedFrom }}">
    <div class="qd-head"><h2 id="dvTitle">@if (\Illuminate\Support\Str::startsWith($openedFrom, 'enquiry ')){{ \Illuminate\Support\Str::after($openedFrom, 'enquiry ') }} · New development @else New development @endif</h2><button type="button" class="qd-x" data-close aria-label="Close">&times;</button></div>
    <div class="qd-body">
        <div class="alert alert-error qd-errors" role="alert" id="dvErrors" @unless ($errors->any()) hidden @endunless>@foreach ($errors->all() as $message)<div>{{ $message }}</div>@endforeach</div>
        <div class="dv-cards">
        @foreach ($types as $k => $t)
            <label class="dv-card"><input type="radio" name="type" value="{{ $k }}" @checked($typeNow === $k)><div><strong>{{ $t['name'] }}</strong><span class="b">{{ $t['sub'] }}</span></div></label>
        @endforeach
        </div>
        @if (\App\Domains\Development\Support\DevelopmentRules::get('tool_depth') !== 'ONE')
        <div class="dv-sec"><h3>Tools</h3>
            <div class="dv-chips"><span class="dv-tool" id="dvFirst"><span id="dvFirstName">Pattern · New</span> <button type="button" id="dvFirstX" aria-label="Remove the pattern">×</button></span>
                <button type="button" class="dv-addtool" data-add="PATTERN" id="dvFirstBack" hidden>+ Pattern</button>
                @foreach (collect(config('pattern_developments.tool_kinds'))->except(['PATTERN', 'FIXTURE']) as $k => $l)@if ($k !== 'CORE_BOX' || $coreOn)<button type="button" class="dv-addtool" data-add="{{ $k }}">+ {{ $l }}</button>@endif @endforeach</div>
            <input type="hidden" name="skip_first_tool" id="dvSkip" value="0">
            <div id="dvTools" style="display:flex;flex-direction:column;gap:6px"></div>
            <datalist id="dvRegister">@foreach ($coreBoxes as $cb)<option value="{{ $cb->code }} — {{ $cb->name }}"></option>@endforeach @foreach ($patterns as $pt)<option value="{{ $pt->code }} — {{ $pt->name }}"></option>@endforeach</datalist>
        </div>
        @endif
        <div class="form-grid">
            <div class="field field-full"><label for="dv-title">Part</label><input id="dv-title" name="title" maxlength="200" value="{{ $old('title') }}" placeholder="What the pattern is for; filled in from the record it opens from"></div>
            @if ($old('enquiry_id'))<input type="hidden" name="enquiry_id" value="{{ $old('enquiry_id') }}">@endif
            <div class="field" data-for="NEW_PART CUSTOMER_PATTERN"><label for="dv-enquiry">Enquiry</label><select id="dv-enquiry" name="enquiry_id" data-search-select><option value="">— none —</option>@foreach ($enquiries as $e)<option value="{{ $e->id }}" data-sub="{{ $e->customer?->name }}" @selected($old('enquiry_id') === $e->id)>{{ $e->code }} — {{ $e->title }}</option>@endforeach</select></div>
            <div class="field" data-for="NEW_PART NEW_PRODUCT MODIFICATION REPLACEMENT CUSTOMER_PATTERN"><label for="dv-customer">Customer</label><select id="dv-customer" name="customer_id" data-search-select><option value="">— choose —</option>@foreach ($customers as $c)<option value="{{ $c->id }}" data-sub="{{ $c->code }}" @selected($old('customer_id') === $c->id)>{{ $c->name }}</option>@endforeach</select></div>
            <div class="field" data-for="NEW_PRODUCT MODIFICATION"><label for="dv-product" data-label-for="MODIFICATION:Part|NEW_PRODUCT:Similar part">Part</label><select id="dv-product" name="product_id" data-search-select><option value="">— none —</option>@foreach ($products as $p)<option value="{{ $p->id }}" data-sub="{{ $p->code }}" @selected($old('product_id') === $p->id)>{{ $p->name }}</option>@endforeach</select></div>
            <div class="field" data-for="CUSTOMER_PATTERN"><label for="dv-pattern">Pattern</label><select id="dv-pattern" name="pattern_id" data-search-select><option value="">— choose —</option>@foreach ($patterns as $pt)<option value="{{ $pt->id }}" data-sub="{{ $pt->code }}" @selected($old('pattern_id') === $pt->id)>{{ $pt->code }} — {{ $pt->name }}</option>@endforeach</select></div>
            <div class="field" data-for="REPLACEMENT MODIFICATION"><label for="dv-pick">Tool to replace or modify</label><select id="dv-pick" name="tool_pick" data-search-select><option value="">— choose —</option>
                @if ($coreBoxes->isNotEmpty())<optgroup label="Core boxes">@foreach ($coreBoxes as $cb)<option value="box:{{ $cb->id }}" data-sub="{{ $cb->code }}{{ $lifeOf($cb->id) }}" @selected($old('core_box_id') === $cb->id)>{{ $cb->code }} — {{ $cb->name }}</option>@endforeach</optgroup>@endif
                <optgroup label="Patterns">@foreach ($patterns as $pt)<option value="pattern:{{ $pt->id }}" data-sub="{{ $pt->code }}{{ $lifeOf($pt->id) }}" @selected($old('pattern_id') === $pt->id)>{{ $pt->code }} — {{ $pt->name }}</option>@endforeach</optgroup></select></div>
            <div class="field" data-for="REPLACEMENT"><label for="dv-reason">Reason</label><select id="dv-reason" name="reason"><option value="">— choose —</option>@foreach (config('pattern_developments.reasons') as $k => $l)<option value="{{ $k }}" @selected(old('reason') === $k)>{{ $l }}</option>@endforeach</select></div>
            <div class="field field-full" data-for="MODIFICATION"><label for="dv-change">Drawing change</label><input id="dv-change" name="drawing_change" maxlength="300" value="{{ old('drawing_change') }}" placeholder="e.g. Rev B → C · flange 22 → 24 mm"></div>
            <div class="field" data-for="REPLACEMENT MODIFICATION"><label for="dv-pays">Who pays</label><select id="dv-pays" name="who_pays"><option value="">— choose —</option>@foreach (config('pattern_developments.who_pays') as $k => $l)<option value="{{ $k }}" @selected(old('who_pays') === $k)>{{ $l }}</option>@endforeach</select></div>
            <div class="field"><label for="dv-by">Pattern by</label><select id="dv-by" name="pattern_by">@foreach (config('pattern_developments.pattern_by') as $k => $l)<option value="{{ $k }}" @selected(old('pattern_by', 'DECIDE_LATER') === $k)>{{ $l }}</option>@endforeach</select></div>
            <div class="field" data-for="REPLACEMENT NEW_PRODUCT"><label for="dv-maker">Pattern maker</label><input id="dv-maker" name="pattern_maker" maxlength="150" value="{{ old('pattern_maker') }}"></div>
            <div class="field"><label for="dv-owner">Owner</label><input id="dv-owner" name="owner_name" maxlength="150" value="{{ old('owner_name', auth()->user()?->name) }}"></div>
            <div class="field"><label for="dv-due">Due</label><input id="dv-due" type="date" name="due_on" value="{{ old('due_on') }}"></div>
        </div>
        <div class="dv-sec"><h3>Copied in</h3><div class="dv-chips" id="dvCopies"></div></div>
        <div class="dv-sec"><h3>Stages for this type</h3><div class="dv-stages" id="dvStages"></div><div class="dv-note" id="dvNote"></div></div>
    </div>
    <div class="qd-foot"><span class="muted small">Opened from {{ $openedFrom }}</span><div><a class="btn" href="{{ $backUrl }}">Cancel</a><button class="btn btn-primary" type="submit" id="dvSubmit">Create development</button></div></div>
</form>
</dialog>
<script>
(function () {
    var T = {!! json_encode($types, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};
    var dlg = document.getElementById('dvDialog'), form = document.getElementById('dv');
    function type() { var r = form.querySelector('[name=type]:checked'); return r ? r.value : ''; }
    function fill(box, items, tag) { box.innerHTML = ''; items.forEach(function (t) { var e = document.createElement(tag); e.textContent = (tag === 'span' ? '✓ ' : '') + t; if (tag === 'span') e.className = 'dv-chip'; box.appendChild(e); }); }
    function apply() {
        var k = type(), t = T[k];
        form.querySelectorAll('[data-for]').forEach(function (g) {
            var on = g.getAttribute('data-for').split(' ').indexOf(k) >= 0; g.hidden = !on;
            g.querySelectorAll('input,select').forEach(function (i) { i.disabled = !on; });
        });
        form.querySelectorAll('.dv-card').forEach(function (c) { c.classList.toggle('sel', c.querySelector('input').checked); });
        var label = document.querySelector('[data-label-for]');
        if (label) { var m = {}; label.getAttribute('data-label-for').split('|').forEach(function (p) { var kv = p.split(':'); m[kv[0]] = kv[1]; }); label.textContent = m[k] || 'Part'; }
        fill(document.getElementById('dvCopies'), t ? t.copies : [], 'span');
        fill(document.getElementById('dvStages'), t ? t.stages : [], 'div');
        document.getElementById('dvNote').textContent = t ? t.note : '';
    }
    var tools = document.getElementById('dvTools'), first = document.getElementById('dvFirst'), n = 0;
    var kinds = @js(config('pattern_developments.tool_kinds')), routes = @js(config('pattern_developments.tool_routes')), makers = @js(config('pattern_developments.pattern_by'));
    function select(name, options) { var s = document.createElement('select'); s.name = name; Object.keys(options).forEach(function (k) { s.add(new Option(options[k], k)); }); return s; }
    function addTool(kind) {
        var row = document.createElement('div'), i = n++, base = 'tools[' + i + ']', hidden = document.createElement('input'), label = document.createElement('input'), x = document.createElement('button'), name = document.createElement('span');
        row.className = 'dv-toolrow'; hidden.type = 'hidden'; hidden.name = base + '[kind]'; hidden.value = kind; name.textContent = kinds[kind];
        label.name = base + '[label]'; label.maxLength = 200; label.placeholder = 'From register or type a label'; label.setAttribute('list', 'dvRegister'); label.setAttribute('aria-label', kinds[kind] + ' name');
        x.type = 'button'; x.textContent = '×'; x.setAttribute('aria-label', 'Remove this tool'); x.addEventListener('click', function () { row.remove(); });
        [hidden, name, select(base + '[route]', routes), label, select(base + '[made_by]', makers), x].forEach(function (e) { row.appendChild(e); });
        tools.appendChild(row); label.focus();
    }
    function firstName() {
        var pick = document.getElementById('dv-pick'), k = type(), name = document.getElementById('dvFirstName');
        if (!name) { return; }
        var picked = pick && !pick.disabled && pick.value ? pick.options[pick.selectedIndex].text.split(' — ')[0] : '';
        var what = picked ? (pick.value.indexOf('box:') === 0 ? 'Core box ' : 'Pattern ') + picked : 'Pattern';
        name.textContent = what + ' · ' + (k === 'REPLACEMENT' ? 'Replace' : k === 'MODIFICATION' ? 'Modify' : 'New');
        document.getElementById('dvFirstX').hidden = k === 'REPLACEMENT' || k === 'MODIFICATION';
    }
    form.querySelectorAll('[data-add]').forEach(function (b) { if (b.id !== 'dvFirstBack') { b.addEventListener('click', function () { addTool(b.getAttribute('data-add')); }); } });
    var firstX = document.getElementById('dvFirstX'), firstBack = document.getElementById('dvFirstBack');
    if (firstX) {
        firstX.addEventListener('click', function () { first.hidden = true; firstBack.hidden = false; document.getElementById('dvSkip').value = '1'; });
        firstBack.addEventListener('click', function () { first.hidden = false; firstBack.hidden = true; document.getElementById('dvSkip').value = '0'; });
    }
    form.addEventListener('change', firstName);
    form.addEventListener('change', apply);
    form.addEventListener('submit', function () { document.getElementById('dvSubmit').disabled = true; });
    dlg.addEventListener('close', function () { window.location = @js($backUrl); });
    apply(); firstName();
    if (dlg.showModal) { dlg.showModal(); } else { dlg.setAttribute('open', ''); }
})();
</script>
@endsection
