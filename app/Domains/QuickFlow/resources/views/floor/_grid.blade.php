{{-- One entry grid of the floor page, a row per pattern. $kind ('made' or 'poured'), $title, $action, $submit; $patterns (each
     with its default station, order and pieces per mould), $stations, $orders / $awaiting, $heats, $patternPhotos, $label.
     Picking a pattern fills in the row's station, pieces per mould and order; each can be changed. Empty rows are ignored. --}}
@php
    $pouring = $kind === 'poured';
    $mine = old('_grid') === $kind;
    $rows = $mine ? array_values((array) old('rows', [])) : [];
    $count = max(5, count($rows));
    $orderChoices = $pouring ? $awaiting : $orders;
    $today = today()->toDateString();
    $autoLabel = fn ($order) => $order ? 'Automatic: '.$order->production_order_number.' '.$order->product?->code : 'Automatic: none open, link later';
@endphp
<div class="panel fg-panel" data-floor-grid="{{ $kind }}">
    <div class="panel-header"><h2>{{ $title }}</h2><span class="muted small">Pick the pattern and type the moulds. Cavities come from the pattern's cavity map; station, order and date are filled in.</span></div>
    <div class="panel-body">
        <form method="POST" action="{{ $action }}">@csrf<input type="hidden" name="_grid" value="{{ $kind }}">
            @if ($mine && $errors->any())<div class="alert alert-error">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
            <div class="fg-head">
                @if ($pouring)
                    <div class="field"><label for="fg-heat">Heat *</label><select id="fg-heat" name="heat_id" required><option value="">—</option>@foreach ($heats as $h)
                        @php $t = $h->thermalTests->first(); @endphp
                        <option value="{{ $h->id }}" @selected($mine && old('heat_id') === $h->id)>{{ $h->heat_number }} · {{ $h->ironGrade?->code }} · {{ $h->furnace?->code }} · thermal: {{ $t ? strtolower(str_replace('_', ' ', $t->result)) : 'none yet' }}</option>@endforeach</select></div>
                @endif
                <div class="field"><label for="fg-{{ $kind }}-date">Date for every row</label><input id="fg-{{ $kind }}-date" type="date" value="{{ $today }}" max="{{ $today }}" data-fg-date></div>
                <div class="field"><label for="fg-{{ $kind }}-by">By</label><input id="fg-{{ $kind }}-by" name="entered_by" maxlength="100" value="{{ $mine ? old('entered_by') : '' }}"></div>
            </div>
            <div class="table-wrap"><table class="fg-table">
                <thead><tr><th>#</th><th>Pattern</th><th>Moulds</th><th>Cavities</th><th>Order</th><th>Station</th><th>Date</th></tr></thead>
                <tbody data-fg-rows>
                    @for ($n = 0; $n < $count; $n++)
                        @include('quickflow::floor._grid-row', ['n' => $n, 'row' => $rows[$n] ?? []])
                    @endfor
                </tbody>
            </table></div>
            <template data-fg-template>@include('quickflow::floor._grid-row', ['n' => '__n__', 'row' => []])</template>
            <div class="actions" style="margin-top:10px"><button type="button" class="btn" data-fg-add>+ Add row</button><button class="btn btn-primary" type="submit">{{ $submit }}</button></div>
            @if ($pouring)<p class="muted small">A missing or failed thermal test shows a warning and is noted on the pour. It does not stop the pour being recorded.</p>@endif
        </form>
    </div>
</div>
@pushOnce('dialogs', 'floor-grid-assets')
<style>
    .fg-head { display: flex; flex-wrap: wrap; gap: 8px 16px; align-items: end; margin-bottom: 8px; }
    .fg-head .field { margin: 0; }
    .fg-table td { padding: 4px 5px; vertical-align: top; }
    .fg-table input, .fg-table select { min-height: 30px; padding: 3px 6px; font-size: 13px; max-width: 100%; }
    .fg-table .fg-pattern { width: 230px; }
    .fg-table .fg-num { width: 80px; }
    .fg-table .fg-order { width: 220px; }
    .fg-table .fg-station { width: 150px; }
    .fg-table input[readonly] { background: color-mix(in srgb, var(--border) 35%, transparent); color: var(--muted); }
    .fg-hint { font-size: 11px; color: var(--muted); margin-top: 2px; }
    .fg-warn { font-size: 12px; color: var(--warning, #b54708); margin-top: 3px; max-width: 230px; white-space: normal; }
    .fg-table .fg-n { width: 30px; color: var(--muted); text-align: center; padding-top: 10px; }
</style>
<script>
(function () {
    function fill(select) {
        var row = select.closest('tr'), o = select.options[select.selectedIndex];
        if (!row || !o) return;
        var station = row.querySelector('[data-fg-station]'), auto = row.querySelector('[data-fg-auto]');
        var cav = row.querySelector('[data-fg-cavities]'), hint = row.querySelector('[data-fg-cavities-hint]'), warn = row.querySelector('[data-fg-warn]');
        if (o.value && station) station.value = o.dataset.station || '';
        // A cavity map gives the count; without one the row asks for it and warns that the pattern makes no known product.
        var mapped = !!(o.value && o.dataset.cavities);
        if (cav) {
            var wasMapped = cav.readOnly;
            cav.readOnly = mapped; cav.required = !!o.value && !mapped;
            cav.value = mapped ? o.dataset.cavities : (wasMapped ? '' : cav.value);
            cav.placeholder = o.value && !mapped ? 'count' : '';
        }
        if (hint) hint.textContent = mapped ? 'from cavity map' : '';
        if (warn) { warn.hidden = !o.value || mapped; var a = warn.querySelector('[data-fg-warn-link]'); if (a && o.dataset.url) a.href = o.dataset.url; }
        if (auto) auto.textContent = o.value ? (o.dataset.order || 'Automatic: none open, link later') : 'Automatic';
    }
    document.addEventListener('change', function (e) {
        if (e.target.matches('[data-fg-pattern]')) fill(e.target);
        if (e.target.matches('[data-fg-date]')) {
            e.target.closest('form').querySelectorAll('[data-fg-row-date]').forEach(function (d) { d.value = e.target.value; });
        }
    });
    document.addEventListener('click', function (e) {
        var add = e.target.closest('[data-fg-add]');
        if (!add) return;
        var form = add.closest('form'), body = form.querySelector('[data-fg-rows]'), n = body.children.length;
        var html = form.querySelector('[data-fg-template]').innerHTML.replace(/__n__/g, n).replace(/__num__/g, n + 1);
        body.insertAdjacentHTML('beforeend', html);
        var date = form.querySelector('[data-fg-date]');
        if (date) body.lastElementChild.querySelector('[data-fg-row-date]').value = date.value;
    });
})();
</script>
@endPushOnce
