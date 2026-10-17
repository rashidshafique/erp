@php $current = $pattern->revisions->firstWhere('is_current', true); @endphp
<div class="panel" id="cavity-map">
    <div class="panel-header"><h2>Cavity map: which product each cavity makes</h2></div>
    <div class="panel-body">
        <p class="muted small">
            Each cavity of a pattern revision makes one product revision, either the whole casting or one
            component of it. The layout is one level: a cope-and-drag mould holds it once, a stack once at every joint.
            Once a map exists, moulds and moulding runs using this revision are checked against it.
        </p>
        <style>
            .layout-row { display: flex; gap: 16px; align-items: flex-start; margin: 6px 0 10px; }
            .layout-fig { flex: none; }
            .layout-text { flex: 1; min-width: 0; }
            .layout-summary { font-size: 12.5px; line-height: 1.6; margin: 2px 0 6px; }
            @media (max-width: 700px) { .layout-row { flex-direction: column; } }
            .layout-form summary { cursor: pointer; font-size: 12.5px; font-weight: 600; color: var(--accent); margin-bottom: 6px; }
            .layout-form { margin-bottom: 10px; }
        </style>

        @include('partials.search-select')
        <style>
            .cavity-grid { margin: 8px 0 26px; }
            .cavity-grid .cg-head { display: flex; flex-wrap: wrap; gap: 6px 18px; align-items: end; margin-bottom: 8px; }
            .cavity-grid .cg-head .field { margin: 0; }
            .cavity-grid .cg-head input { width: 110px; }
            .cavity-grid .cg-head p { margin: 0 0 6px; max-width: 60ch; }
            .cg-table { font-size: 12.5px; }
            .cg-table td { padding: 4px 5px; vertical-align: top; }
            .cg-table input, .cg-table select { min-height: 28px; padding: 3px 6px; font-size: 12.5px; max-width: 100%; }
            .cg-table .cg-product { width: 170px; }
            .cg-table select[name$="[cavity_role]"] { width: 138px; }
            .cg-table input[name$="[component_label]"] { width: 120px; }
            .cg-table input[name$="[notes]"] { width: 150px; }
            .cg-table .cg-num { width: 78px; }
            .cg-table .cg-n { width: 54px; text-align: center; }
            .cg-table .bad { border-color: var(--danger) !important; }
            .cg-table tr.has-error > td { background: color-mix(in srgb, var(--danger) 6%, transparent); }
            .cg-table tr.cg-errors td { color: var(--danger); font-size: 12px; padding-top: 0; }
        </style>

        @foreach ($pattern->revisions as $revision)
            <h3 style="margin-top:22px" id="cavities-{{ $revision->id }}">{{ $revision->label() }}
                <span class="muted small">({{ $revision->cavityMap->count() }} of {{ $revision->cavities_per_mould ?? '?' }} cavities linked)</span>
            </h3>
            @include('patterns._layout', ['revision' => $revision, 'pattern' => $pattern, 'assembled' => in_array($revision->id, $assembledRevisions, true)])
            @if ($revision->isSuperseded() && $revision->cavityMap->isNotEmpty())
                <div class="alert alert-warning">{{ \App\Http\Controllers\PatternCavityController::supersededWarning($revision) }}</div>
            @endif
            @include('patterns._cavity-grid', ['revision' => $revision, 'pattern' => $pattern, 'productRevisions' => $productRevisions, 'assembled' => in_array($revision->id, $assembledRevisions, true)])
        @endforeach
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
                count.addEventListener('input', sync);
            });
        })();
        </script>
    </div>
</div>
