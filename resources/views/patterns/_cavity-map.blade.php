@php $current = $pattern->revisions->firstWhere('is_current', true); @endphp
<div class="panel" id="cavity-map">
    <div class="panel-header"><h2>Cavity map by revision: which product each cavity makes</h2></div>
    <div class="panel-body">
        <style>
            .layout-row { display: flex; gap: 16px; align-items: flex-start; margin: 6px 0 10px; }
            .layout-fig { flex: none; }
            .layout-text { flex: 1; min-width: 0; }
            .layout-summary { font-size: 12.5px; line-height: 1.6; margin: 2px 0 6px; }
            @media (max-width: 768px) { .layout-row { flex-direction: column; } }
            .layout-form summary { cursor: pointer; font-size: 12.5px; font-weight: 600; color: var(--accent); margin-bottom: 6px; }
            .layout-form { margin-bottom: 10px; }
            .lf-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(135px, 1fr)); gap: 6px 10px; align-items: end; }
            .lf-grid .field { margin: 0; }
            .lf-grid .lf-wide { grid-column: span 2; }
            .lf-grid .lf-full { grid-column: 1 / -1; }
            .lf-grid label { font-size: 12px; margin-bottom: 2px; }
            .lf-grid input, .lf-grid select { min-height: 28px; padding: 3px 6px; font-size: 12.5px; }
            .lf-actions { display: flex; gap: 10px; align-items: center; }
            .lf-flasks > summary { cursor: pointer; font-size: 12px; font-weight: 600; }
        </style>

        @include('partials.search-select')
        <style>
            .cavity-grid { margin: 8px 0 26px; }
            .cavity-grid .cg-head { display: flex; flex-wrap: wrap; gap: 6px 18px; align-items: end; margin-bottom: 8px; }
            .cavity-grid .cg-head .field { margin: 0; }
            .cavity-grid .cg-head input { width: 110px; }
            .cavity-grid .cg-head p { margin: 0 0 6px; max-width: 60ch; }
            .cg-tools { display: flex; flex-wrap: wrap; gap: 6px 12px; align-items: center; margin: 0 0 6px; }
            .cg-table { font-size: 12.5px; }
            .cg-table td { padding: 4px 5px; vertical-align: top; }
            .cg-table input, .cg-table select { min-height: 28px; padding: 3px 6px; font-size: 12.5px; max-width: 100%; }
            .cg-table .cg-product { width: 170px; }
            .cg-table select[name$="[cavity_role]"] { width: 138px; }
            .cg-table input[name$="[component_label]"] { width: 120px; }
            .cg-table input[name$="[notes]"] { width: 150px; }
            .cg-table .cg-num { width: 78px; }
            .cg-table .cg-mark { width: 70px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; text-transform: uppercase; }
            .cg-table .cg-n { width: 54px; text-align: center; }
            .cg-table .bad { border-color: var(--danger) !important; }
            .cg-table tr.has-error > td { background: color-mix(in srgb, var(--danger) 6%, transparent); }
            .cg-table tr.cg-errors td { color: var(--danger); font-size: 12px; padding-top: 0; }
        </style>

        @php $older = $pattern->revisions->reject(fn ($r) => $r->is_current); @endphp
        @foreach ($pattern->revisions as $revision)
            @php
                // Only the current revision is open; an older one opens when it has a save error to show.
                $open = $revision->is_current || in_array($revision->id, [old('_map'), old('_layout')], true) || ! $current;
            @endphp
            @if (! $revision->is_current && $loop->first === false && $older->first()?->id === $revision->id)
                <h3 class="older-heading">Older revisions <span class="muted small">({{ $older->count() }}, kept for traceability)</span></h3>
            @endif
            <details class="rev-block {{ $revision->is_current ? 'is-current' : '' }}" id="cavities-{{ $revision->id }}" @if ($open) open @endif>
                <summary>
                    <span class="rev-tag">Revision</span> <strong>{{ $revision->label() }}</strong>
                    <span class="muted small">{{ $revision->cavityMap->count() }} of {{ $revision->cavities_per_mould ?? '?' }} cavities linked @if ($revision->isSuperseded()) · superseded {{ $revision->superseded_at->displayDate() }}@endif</span>
                </summary>
                <div class="rev-body">
                @include('patterns._layout', ['revision' => $revision, 'pattern' => $pattern, 'assembled' => in_array($revision->id, $assembledRevisions, true)])
                @if ($revision->isSuperseded() && $revision->cavityMap->isNotEmpty())
                    <div class="alert alert-warning">{{ \App\Http\Controllers\PatternCavityController::supersededWarning($revision) }}</div>
                @endif
                @include('patterns._cavity-grid', ['revision' => $revision, 'pattern' => $pattern, 'productRevisions' => $productRevisions, 'assembled' => in_array($revision->id, $assembledRevisions, true)])
                </div>
            </details>
        @endforeach
        @if ($pattern->revisions->isEmpty() && ! $pattern->trashed())
            @php($firstRevision = tap((new \App\Models\PatternRevision)->forceFill(['id' => 'first']), fn ($r) => $r->setRelation('cavityMap', collect())))
            <p class="muted small">This pattern has no revision yet. Link a product below and its first revision (Rev 0) is recorded when you save.</p>
            @include('patterns._cavity-grid', ['revision' => $firstRevision, 'pattern' => $pattern, 'productRevisions' => $productRevisions, 'assembled' => false, 'action' => route('patterns.cavities.first-map', $pattern)])
        @endif
        <style>
            .rev-block { border: 1px solid var(--border); border-radius: 10px; padding: 0 14px; margin: 12px 0; }
            .rev-block[open] { padding-bottom: 4px; }
            .rev-block.is-current { border-color: var(--accent); }
            .rev-block > summary .rev-tag { font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: var(--muted); }
            .rev-block > .rev-body { border-left: 3px solid var(--border); padding-left: 12px; margin: 0 0 10px; }
            .rev-block.is-current > .rev-body { border-left-color: var(--accent); }
            .rev-body h4 { margin: 10px 0 2px; font-size: 13px; }
            .rev-block > summary { display: flex; flex-wrap: wrap; gap: 4px 12px; align-items: baseline; padding: 10px 0; cursor: pointer; font-size: 14px; }
            .rev-block:not(.is-current) > summary strong { font-weight: 600; color: var(--muted); }
            .older-heading { margin: 22px 0 4px; font-size: 13px; text-transform: uppercase; letter-spacing: .05em; color: var(--muted); }
        </style>
        <script>
        (function () {
            // A link to an older revision (from a product's Patterns tab) opens it.
            function openTarget() {
                var id = decodeURIComponent(location.hash.slice(1)), el = id && document.getElementById(id);
                if (el && el.tagName === 'DETAILS' && el.classList.contains('rev-block')) { el.open = true; el.scrollIntoView({ block: 'start' }); }
            }
            window.addEventListener('hashchange', openTarget);
            openTarget();
        })();
        </script>
        <script>
        (function () {
            document.querySelectorAll('[data-cavity-grid]').forEach(function (form) {
                var count = form.querySelector('[data-cavity-count]'), body = form.querySelector('[data-cavity-rows]'), tpl = form.querySelector('[data-cavity-template]');
                var parked = {};   // rows removed by lowering the count keep their values if it goes back up
                function rows() { return body.querySelectorAll('tr[data-cavity-row]'); }
                function sync() {
                    var want = Math.max(0, Math.min(200, parseInt(count.value, 10) || 0)), have = rows().length;
                    for (var n = have + 1; n <= want; n++) {
                        if (parked[n]) { body.appendChild(parked[n]); delete parked[n]; continue; }
                        var holder = document.createElement('tbody'); holder.innerHTML = tpl.innerHTML.split('__n__').join(n);
                        body.appendChild(holder.querySelector('tr'));
                    }
                    for (var m = have; m > want; m--) {
                        var tr = body.querySelector('tr[data-cavity-row="' + m + '"]'), err = body.querySelector('[data-cavity-errors="' + m + '"]');
                        if (err) err.remove();
                        if (tr) { parked[m] = tr; tr.remove(); }
                    }
                }
                // One product on every cavity, the usual case for a single-casting plate: the product chosen on one cavity goes on the rest.
                var repeat = form.querySelector('[data-cavity-repeat]'), repeatMsg = form.querySelector('[data-cavity-repeat-msg]');
                if (repeat) {
                    repeat.addEventListener('click', function () {
                        var selects = [].slice.call(body.querySelectorAll('select[name$="[product_revision_id]"]'));
                        var chosen = selects.map(function (s) { return s.value; }).filter(function (v, i, a) { return v && a.indexOf(v) === i; });
                        if (chosen.length === 0) { repeatMsg.textContent = 'Choose the product on one cavity first.'; return; }
                        if (chosen.length > 1) { repeatMsg.textContent = 'More than one product is chosen, so there is no single product to repeat.'; return; }
                        var done = 0;
                        selects.forEach(function (s) {
                            if (s.value) return;
                            s.value = chosen[0]; s.dispatchEvent(new Event('change', { bubbles: true })); done++;
                        });
                        repeatMsg.textContent = done ? 'Repeated on ' + done + (done === 1 ? ' more cavity.' : ' more cavities.') : 'Every cavity already has the product.';
                    });
                }
                count.addEventListener('input', sync);
                // With a layout form above, its cavity box is the one count: the grid follows it as it is typed.
                var source = count.dataset.cavitySource && document.getElementById(count.dataset.cavitySource);
                if (source) {
                    var follow = function () { count.value = source.value; sync(); };
                    source.addEventListener('input', follow); source.addEventListener('change', follow);
                }
            });
        })();
        </script>
    </div>
</div>
