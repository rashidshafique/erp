@extends('layouts.app')

@section('title', 'Bulk moulding entry')
@section('breadcrumb', 'Moulding runs / Bulk entry')

@section('content')
@include('partials.editable-rows')
@include('partials.search-select')
@php
    $oldRows = old('rows');
    $failed = $oldRows !== null;
    $gridRows = $failed ? $oldRows : ($rows ?: ['n1' => ['run_date' => $header['run_date'], 'shift' => $header['shift']]]);
    $h = old('header', $header);
    $openedDate = old('opened_date', $opened ? request('date') : null);
    $openedShift = old('opened_shift', $opened ? request('shift') : null);
    $rowErrors = [];
    foreach ($errors->getMessages() as $field => $messages) {
        if (preg_match('/^rows\.([^.]+)\.(.+)$/', $field, $m)) {
            $rowErrors[$m[1]][$m[2]] = $messages[0];
        }
    }
    $lists = compact('patterns', 'patternPhotos', 'stations', 'stationCrew', 'orders', 'defects', 'shifts');
    $revisionInfo = $patterns->flatMap->revisions->mapWithKeys(fn ($r) => [$r->id => ['pattern' => $r->pattern_id, 'superseded' => $r->isSuperseded(), 'cavities' => $r->cavityMap->pluck('cavity_number')->implode(', '), 'products' => $r->cavityMap->pluck('productRevision.product_id')->filter()->unique()->values()]]);
    $revisionsOf = $patterns->mapWithKeys(fn ($p) => [$p->id => $p->revisions->pluck('id')]);
    $orderProduct = $orders->pluck('product_id', 'id');
    $currentOf = $patterns->mapWithKeys(fn ($p) => [$p->id => $p->revisions->firstWhere('is_current', true)?->id]);
    $made = collect($gridRows)->sum(fn ($r) => (int) ($r['moulds_made_qty'] ?? 0));
    $rejected = collect($gridRows)->sum(fn ($r) => (int) ($r['damaged_mould_qty'] ?? 0));
@endphp
<div class="page-heading">
    <div>
        <h1>Moulding runs</h1>
        <p>Enter a shift's runs in one go, or open a shift to correct what was saved. Rows are saved all together or not at all.</p>
    </div>
    <div class="actions"><a href="{{ route('moulding-runs.create') }}" class="btn">+ Record one run</a></div>
</div>
@include('moulding-runs._tabs', ['active' => 'bulk'])

<style>
    .bulk-bar { display: flex; flex-wrap: wrap; gap: 10px 16px; align-items: end; }
    .bulk-bar .field { margin: 0; }
    table.bulk { font-size: 12px; }
    table.bulk th { white-space: nowrap; font-size: 11px; }
    table.bulk td { padding: 3px 4px; vertical-align: top; }
    table.bulk .ed input:not([type=checkbox]), table.bulk .ed select { min-height: 26px; padding: 2px 5px; font-size: 12px; border-radius: 5px; }
    table.bulk .w-date { width: 122px; } table.bulk .w-shift { width: 82px; } table.bulk .w-code { width: 78px; }
    table.bulk .w-pat { width: 170px; } table.bulk .w-rev { width: 150px; } table.bulk .w-cav { width: 62px; }
    table.bulk .w-order { width: 120px; } table.bulk .crew { font-size: 10.5px; margin-top: 2px; white-space: nowrap; } table.bulk .w-num { width: 60px; } table.bulk .w-notes { width: 140px; }
    table.bulk .ro.clip { max-width: 140px; }
    table.bulk .bad { border-color: var(--danger) !important; }
    table.bulk tr.has-error > td { background: color-mix(in srgb, var(--danger) 6%, transparent); }
    table.bulk tr.row-errors td { color: var(--danger); font-size: 12px; padding: 0 6px 6px; border-top: 0; }
    table.bulk .warn { display: block; color: var(--warning); font-size: 11px; margin-top: 2px; }
    table.bulk .warn[hidden] { display: none; }
    table.bulk .tools { white-space: nowrap; }
    table.bulk th .fill { border: 0; background: transparent; color: var(--accent); cursor: pointer; padding: 0 2px; font-size: 12px; }
    #bulk-form kbd { font: 600 10.5px ui-monospace, Menlo, monospace; padding: 1px 5px; margin-left: 6px; border-radius: 4px; border: 1px solid color-mix(in srgb, currentColor 35%, transparent); opacity: .85; }
    table.bulk tfoot td { font-weight: 700; border-top: 2px solid var(--border); padding: 6px 4px; }
</style>

<div class="panel"><div class="panel-body">
    <form method="GET" action="{{ route('moulding-runs.bulk') }}" class="bulk-bar">
        <div class="field"><label for="open-date">Open a saved shift</label><input id="open-date" type="date" name="date" value="{{ request('date', today()->toDateString()) }}" required></div>
        <div class="field"><label for="open-shift">Shift</label><select id="open-shift" name="shift"><option value="">All shifts</option>@foreach ($shifts as $s)<option @selected(request('shift') === $s)>{{ $s }}</option>@endforeach</select></div>
        <button class="btn" type="submit">Open</button>
        <a class="btn" href="{{ route('moulding-runs.bulk') }}">New grid</a>
        @if ($opened)<span class="muted small">{{ $runs->count() }} saved {{ $runs->count() === 1 ? 'run' : 'runs' }} on {{ \Illuminate\Support\Carbon::parse(request('date'))->format('D d M Y') }}{{ request('shift') ? ', '.request('shift') : '' }}. Press the pencil to correct one.</span>@endif
    </form>
</div></div>

<form method="POST" action="{{ route('moulding-runs.bulk.save') }}" id="bulk-form">
    @csrf
    <input type="hidden" name="opened_date" value="{{ $openedDate }}">
    <input type="hidden" name="opened_shift" value="{{ $openedShift }}">

    <div class="panel"><div class="panel-header"><h2>Defaults for new rows</h2><span class="muted small">Every new row starts with these; a blank cell in a row also falls back to them. Team and sand come from the station; change them on a run's own page.</span></div><div class="panel-body">
        <div class="bulk-bar">
            <div class="field"><label for="h-date">Date</label><input id="h-date" type="date" name="header[run_date]" value="{{ $h['run_date'] ?? '' }}" data-header="run_date"></div>
            <div class="field"><label for="h-shift">Shift</label><select id="h-shift" name="header[shift]" data-header="shift"><option value="">—</option>@foreach ($shifts as $s)<option @selected(($h['shift'] ?? null) === $s)>{{ $s }}</option>@endforeach</select></div>
            <div class="field"><label for="h-station">Station</label><select id="h-station" name="header[moulding_station_id]" data-header="moulding_station_id"><option value="">—</option>@foreach ($stations as $s)<option value="{{ $s->id }}" @selected(($h['moulding_station_id'] ?? null) === $s->id)>{{ $s->code }} — {{ $s->name }}</option>@endforeach</select></div>
        </div>
    </div></div>

    <div class="panel">
        <div class="panel-header"><h2>Runs</h2><span class="muted small">Rejected moulds are counted out of moulds made. Order “Automatic” picks the open order that needs the pattern.</span></div>
        <div class="table-wrap">
            <table class="bulk" data-editable-many>
                <thead><tr>
                    <th>Run</th>
                    <th>Date <button type="button" class="fill" data-fill-down="run_date" title="Fill down" aria-label="Fill date down">↓</button></th>
                    <th>Shift <button type="button" class="fill" data-fill-down="shift" title="Fill down" aria-label="Fill shift down">↓</button></th>
                    <th>Station <button type="button" class="fill" data-fill-down="moulding_station_id" title="Fill down" aria-label="Fill station down">↓</button></th>
                    <th>Pattern <button type="button" class="fill" data-fill-down="pattern_id" title="Fill down" aria-label="Fill pattern down">↓</button></th>
                    <th>Revision <button type="button" class="fill" data-fill-down="pattern_revision_id" title="Fill down" aria-label="Fill revision down">↓</button></th>
                    <th>Cavities</th><th>Order</th><th>Made</th><th>Rejected</th><th>Defect</th><th>Remarks</th><th>Duplicate?</th><th></th>
                </tr></thead>
                <tbody id="bulk-rows">
                    @foreach ($gridRows as $key => $row)
                        @php $run = filled($row['id'] ?? null) ? ($runs[$row['id']] ?? \App\Models\MouldingRun::with('productionOrder', 'defect')->find($row['id'])) : null; @endphp
                        @include('moulding-runs._bulk-row', $lists + ['key' => $key, 'row' => $row, 'run' => $run, 'open' => ! $run || isset($rowErrors[$key]), 'rowErrors' => $rowErrors[$key] ?? []])
                    @endforeach
                </tbody>
                <tfoot><tr>
                    <td colspan="8" style="text-align:right">Total</td>
                    <td class="mono" data-total-made>{{ $made }}</td>
                    <td class="mono" data-total-rejected>{{ $rejected }}</td>
                    <td colspan="4" class="mono" data-total-pct>{{ $made > 0 ? \App\Support\Formats::number(100 * $rejected / $made, 1).' % rejected' : '' }}</td>
                </tr></tfoot>
            </table>
        </div>
        <div class="panel-body" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">
            <button type="button" class="btn" id="bulk-add" title="Add a row (Alt+N)">+ Add row <kbd data-key="alt">Alt+N</kbd></button>
            <span class="muted small">Blank new rows are ignored when saving.</span>
            <span style="flex:1"></span>
            <button type="submit" class="btn btn-primary" name="action" value="save" id="bulk-save" title="Save all rows (Ctrl+S)">Save all rows <kbd data-key="mod">Ctrl+S</kbd></button>
        </div>
    </div>

    <details class="panel" @if ($errors->has('paste')) open @endif>
        <summary class="panel-header" style="cursor:pointer"><h2>Paste from a spreadsheet</h2></summary>
        <div class="panel-body">
            <p class="muted small" style="margin-top:0">Copy rows from Excel or Google Sheets and paste them here. Columns in this order (later ones may be left off): date, shift, station code, pattern code, revision code (blank = current), cavities, order number, made, rejected, defect code, remarks. Team and sand come from the station. A heading row is skipped.</p>
            <div class="field field-full"><textarea name="paste" rows="5" style="max-width:none;font-family:ui-monospace,Menlo,monospace" aria-label="Rows to paste">{{ old('paste') }}</textarea></div>
            <button type="submit" class="btn" name="action" value="paste" formnovalidate>Load pasted rows</button>
        </div>
    </details>
</form>

<template id="bulk-template">
    @include('moulding-runs._bulk-row', $lists + ['key' => '__k__', 'row' => [], 'run' => null, 'open' => true, 'rowErrors' => []])
</template>

<script>
(function () {
    var body = document.getElementById('bulk-rows'), form = document.getElementById('bulk-form');
    var revisions = @json($revisionInfo), currentOf = @json($currentOf), stationOf = @json($patterns->pluck('preferred_moulding_station_id', 'id')), revisionsOf = @json($revisionsOf), orderProduct = @json($orderProduct), workListOf = @json($workListRevisions), lastRunOf = @json($lastRunRevisions), crewOf = @json($stationCrew), n = 0;
    function field(tr, f) { return tr.querySelector('[name$="[' + f + ']"]'); }
    function rowsOf() { return [].slice.call(body.querySelectorAll('tr[data-bulk-row]')); }

    // The revision a row starts with: its work list, else the pattern's last completed run, else one that makes the order's product, else the current one.
    function suggestRevision(tr, pat) {
        var d = (field(tr, 'run_date') || {}).value, sh = ((field(tr, 'shift') || {}).value || '').toLowerCase(), st = (field(tr, 'moulding_station_id') || {}).value || '';
        var found = workListOf[d + '|' + sh + '|' + st + '|' + pat] || workListOf[d + '|' + sh + '||' + pat] || lastRunOf[pat];
        if (found) return found;
        var product = orderProduct[(field(tr, 'production_order_id') || {}).value];
        if (product) { found = (revisionsOf[pat] || []).filter(function (id) { return revisions[id] && revisions[id].products.indexOf(product) !== -1; })[0]; }
        return found || currentOf[pat] || '';
    }
    function syncRevision(tr) {
        var pat = field(tr, 'pattern_id'), rev = field(tr, 'pattern_revision_id');
        if (!pat || !rev) return;
        rev.disabled = pat.value === ''; rev.title = rev.disabled ? 'Choose the pattern first' : 'Pattern revision';
        if (rev.disabled) rev.value = '';
        [].forEach.call(rev.querySelectorAll('optgroup'), function (g) { g.hidden = pat.value !== '' && g.dataset.patternId !== pat.value; g.disabled = g.hidden; });
        var info = revisions[rev.value];
        if (pat.value && (!info || info.pattern !== pat.value)) { rev.value = suggestRevision(tr, pat.value); info = revisions[rev.value]; }
        var warn = tr.querySelector('.warn'); if (warn) warn.hidden = !(info && info.superseded);
        var cav = field(tr, 'cavity_numbers');
        if (cav) { cav.placeholder = info && info.cavities ? info.cavities : 'all'; cav.title = info && info.cavities ? 'Mapped cavities: ' + info.cavities : 'No cavity map'; }
    }
    function syncCrew(tr) {
        var st = field(tr, 'moulding_station_id'), crew = tr.querySelector('[data-crew]');
        if (!st || !crew) return;
        crew.textContent = st.dataset.saved && st.value === st.dataset.saved ? crew.dataset.savedCrew : (crewOf[st.value] || '');
    }
    function totals() {
        var made = 0, rej = 0;
        rowsOf().forEach(function (tr) { made += parseInt((field(tr, 'moulds_made_qty') || {}).value, 10) || 0; rej += parseInt((field(tr, 'damaged_mould_qty') || {}).value, 10) || 0; });
        form.querySelector('[data-total-made]').textContent = made;
        form.querySelector('[data-total-rejected]').textContent = rej;
        form.querySelector('[data-total-pct]').textContent = made > 0 ? (100 * rej / made).toFixed(window.decimalPlaces) + ' % rejected' : '';
    }
    function addRow(values, after) {
        var key = 'n' + Date.now().toString(36) + (n++);
        var html = document.getElementById('bulk-template').innerHTML.split('__k__').join(key);
        var holder = document.createElement('tbody'); holder.innerHTML = html;
        var tr = holder.querySelector('tr');
        document.querySelectorAll('[data-header]').forEach(function (h) { var f = field(tr, h.dataset.header); if (f && h.value) f.value = h.value; });
        Object.keys(values || {}).forEach(function (k) { var f = field(tr, k); if (f) { if (f.type === 'checkbox') f.checked = false; else f.value = values[k]; } });
        if (after) { var next = after.nextElementSibling; if (next && next.classList.contains('row-errors')) after = next; after.after(tr); } else body.appendChild(tr);
        syncRevision(tr); syncCrew(tr); totals();
        var first = tr.querySelector('.ed input, .ed select'); if (first) first.focus();
        return tr;
    }
    function valuesOf(tr) {
        var v = {};
        tr.querySelectorAll('input, select').forEach(function (c) { var m = c.name.match(/\[([a-z_]+)\]$/); if (m && m[1] !== 'id' && m[1] !== 'confirm_duplicate' && c.type !== 'hidden') v[m[1]] = c.value; });
        return v;
    }

    document.getElementById('bulk-add').addEventListener('click', function () { addRow({}); });

    // Shortcuts: Alt+N adds a row, Ctrl+S (⌘S on a Mac) saves every row.
    var mac = /Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent);
    if (mac) {
        form.querySelectorAll('kbd[data-key="mod"]').forEach(function (k) { k.textContent = '⌘S'; k.parentNode.title = 'Save all rows (⌘S)'; });
        form.querySelectorAll('kbd[data-key="alt"]').forEach(function (k) { k.textContent = '⌥N'; k.parentNode.title = 'Add a row (⌥N)'; });
    }
    document.addEventListener('keydown', function (e) {
        if (e.altKey && !e.ctrlKey && !e.metaKey && e.code === 'KeyN') { e.preventDefault(); addRow({}); }
        else if ((mac ? e.metaKey : e.ctrlKey) && !e.altKey && e.code === 'KeyS') { e.preventDefault(); form.requestSubmit(document.getElementById('bulk-save')); }
    });
    body.addEventListener('click', function (e) {
        var dup = e.target.closest('[data-row-duplicate]'), del = e.target.closest('[data-row-delete]');
        if (dup) { var src = dup.closest('tr'); addRow(valuesOf(src), src); }
        if (del) { var tr = del.closest('tr'), err = tr.nextElementSibling; if (err && err.classList.contains('row-errors')) err.remove(); tr.remove(); totals(); }
    });
    form.addEventListener('click', function (e) {
        var fill = e.target.closest('[data-fill-down]'); if (!fill) return;
        var name = fill.dataset.fillDown, open = rowsOf().filter(function (tr) { return tr.classList.contains('is-editing'); });
        if (!open.length) return;
        var value = field(open[0], name).value;
        // A revision only fills rows of the same pattern; filling a pattern brings its revision with it.
        open.slice(1).forEach(function (tr) {
            if (name === 'pattern_revision_id' && field(tr, 'pattern_id').value !== field(open[0], 'pattern_id').value) return;
            field(tr, name).value = value;
            if (name === 'pattern_id') { field(tr, 'pattern_revision_id').value = field(open[0], 'pattern_revision_id').value; }
            syncRevision(tr);
        });
    });
    body.addEventListener('change', function (e) {
        var tr = e.target.closest('tr[data-bulk-row]'); if (!tr) return;
        if (e.target.matches('[data-pattern], [data-revision]')) syncRevision(tr);
        if (e.target.matches('[data-pattern]')) { var st = field(tr, 'moulding_station_id'); if (st && !st.value && stationOf[e.target.value]) { st.value = stationOf[e.target.value]; syncCrew(tr); } }
        if (e.target.matches('[name$="[moulding_station_id]"]')) syncCrew(tr);
    });
    body.addEventListener('input', function (e) { if (e.target.matches('[data-made], [data-rejected]')) totals(); });
    body.addEventListener('click', function (e) { if (e.target.closest('[data-row-cancel]')) var tr = e.target.closest('tr'); setTimeout(function () { syncRevision(tr); syncCrew(tr); totals(); }, 0); });
    rowsOf().forEach(syncRevision);
})();
</script>
@endsection
