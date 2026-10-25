{{-- A dialog for recording a thermal test, and (when the heat is fixed) a chemical analysis, from any screen but the record page.
     Opened by any [data-quick="$id"] button; add data-heat="<heat id>" to a button to pick the heat when the dialog offers a picker.
     $id (default 'qd-record-test'). The heat is either fixed, $heat, or chosen from $heats (selected: $selected). Tabs: thermal
     always, chemistry only for a fixed heat. A saved test returns to the page it was opened from; an error reopens the dialog. --}}
@php
    $id = $id ?? 'qd-record-test';
    $fixed = $heat ?? null;
    $heats = $heats ?? collect();
    $isMine = old('_quick') === $id;
    $tab = $isMine ? old('_tab', 'thermal') : 'thermal';
    $gauges = $gauges ?? \App\Models\Gauge::where('status', 'IN_SERVICE')->orderBy('code')->get();
    $ip = $id.'-';
    // The heat as the dialog title shows it: line, grade and the grade's target CE.
    $ceRange = fn ($h) => $h?->ironGrade && ($h->ironGrade->ce_min !== null || $h->ironGrade->ce_max !== null)
        ? rtrim(rtrim((string) ($h->ironGrade->ce_min ?? '…'), '0'), '.').'–'.rtrim(rtrim((string) ($h->ironGrade->ce_max ?? '…'), '0'), '.').'%' : null;
    $heatInfo = fn ($h) => 'Line: '.($h->furnace?->code ?? '—').' · Grade: '.($h->ironGrade?->code ?? 'none').($ceRange($h) ? ' · Target CE: '.$ceRange($h) : '');
@endphp
@pushOnce('dialogs', $id)
<dialog id="{{ $id }}" class="qd qd-wide" aria-labelledby="{{ $id }}-title" @if ($isMine && $errors->any()) data-reopen="{{ json_encode($errors->getMessages()) }}" @endif>
    <div class="qd-head"><h2 id="{{ $id }}-title" class="qd-heat-title">
        @if ($fixed)
            <span class="qd-heat"><x-heat-light :heat="$fixed" /> Heat <strong>{{ $fixed->heat_number }}</strong></span>
            <span class="qd-heat-meta">{{ $heatInfo($fixed) }}</span>
        @else
            <span class="qd-heat">Record a test</span><span class="qd-heat-meta" data-heat-info></span>
        @endif
    </h2><button type="button" class="qd-x" data-close aria-label="Close">&times;</button></div>
    @if ($fixed)
        <div class="qd-tabs" role="tablist">
            <button type="button" role="tab" data-rt-tab="thermal" aria-selected="{{ $tab === 'thermal' ? 'true' : 'false' }}">Thermal test</button>
            <button type="button" role="tab" data-rt-tab="chemistry" aria-selected="{{ $tab === 'chemistry' ? 'true' : 'false' }}">Chemical analysis</button>
        </div>
    @endif
    <div class="qd-body">
        <div class="alert alert-error qd-errors" role="alert" @unless ($isMine && $errors->any()) hidden @endunless>@if ($isMine)@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach @endif</div>

        <form method="POST" action="{{ route('thermal.store') }}" enctype="multipart/form-data" data-rt-pane="thermal" @if ($tab !== 'thermal') hidden @endif>@csrf
            <input type="hidden" name="_quick" value="{{ $id }}"><input type="hidden" name="_tab" value="thermal"><input type="hidden" name="_dialog" value="1">
            @include('thermal._fields', ['ip' => $ip.'t-', 'mine' => $isMine && $tab === 'thermal', 'fixedHeat' => $fixed, 'heats' => $heats, 'selected' => $isMine ? old('heat_id') : ($heats->first()?->id), 'gauges' => $gauges, 'heatInfo' => $heatInfo])
            <div class="qd-foot"><button type="button" class="btn" data-close>Cancel</button><button class="btn btn-primary" type="submit">Analyse &amp; save test</button></div>
        </form>

        @if ($fixed)
        <form method="POST" action="{{ route('heats.chemistry.store', $fixed) }}" data-rt-pane="chemistry" @if ($tab !== 'chemistry') hidden @endif>@csrf
            <input type="hidden" name="_quick" value="{{ $id }}"><input type="hidden" name="_tab" value="chemistry">
            <p class="muted small">{{ $fixed->ironGrade ? 'Judged against '.$fixed->ironGrade->code.'.' : 'This heat has no iron grade, so results are saved but not judged.' }} Carbon equivalent is worked out as C + (Si + P) / 3.</p>
            @include('heats._chemistry-fields', ['heat' => $fixed, 'ip' => $ip.'c-', 'mine' => $isMine && $tab === 'chemistry'])
            <div class="qd-foot"><button type="button" class="btn" data-close>Cancel</button><button class="btn btn-primary" type="submit">Save analysis</button></div>
        </form>
        @endif
    </div>
</dialog>
@endPushOnce
@include('partials.quick-dialog-assets')
@include('thermal._fields-assets')
@pushOnce('dialogs', 'record-test-dialog-assets')
<style>
    dialog.qd.qd-wide { width: min(1200px, calc(100vw - 32px)); }
    .qd-tabs { display: flex; gap: 2px; padding: 0 16px; border-bottom: 1px solid var(--border); }
    .qd-tabs button { appearance: none; border: 0; background: transparent; padding: 10px 14px; font: inherit; font-weight: 600; color: var(--muted); cursor: pointer; border-bottom: 3px solid transparent; margin-bottom: -1px; }
    .qd-tabs button[aria-selected="true"] { color: var(--accent); border-bottom-color: var(--accent); }
    dialog.qd-wide .qd-body { padding-bottom: 0; }
    .qd-heat-title { display: flex; flex-wrap: wrap; align-items: baseline; gap: 4px 16px; margin: 0; font-size: 16px; }
    .qd-heat-title .qd-heat { display: inline-flex; align-items: center; gap: 6px; }
    .qd-heat-title .qd-heat strong { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 18px; }
    .qd-heat-meta { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 13px; font-weight: 400; color: var(--muted); }
    dialog.qd-wide .qd-foot { position: sticky; bottom: 0; background: var(--surface); padding: 12px 0 14px; margin-top: 8px; border-top: 1px solid var(--border); }
</style>
<script>
(function () {
    function show(dlg, tab) {
        dlg.querySelectorAll('[data-rt-pane]').forEach(function (f) { f.hidden = f.dataset.rtPane !== tab; });
        dlg.querySelectorAll('[data-rt-tab]').forEach(function (b) { b.setAttribute('aria-selected', b.dataset.rtTab === tab ? 'true' : 'false'); });
    }
    document.addEventListener('click', function (e) {
        var tabBtn = e.target.closest('[data-rt-tab]');
        if (tabBtn) { show(tabBtn.closest('dialog'), tabBtn.dataset.rtTab); return; }
        var opener = e.target.closest('[data-quick][data-heat]');
        if (opener) {
            var dlg = document.getElementById(opener.dataset.quick), sel = dlg && dlg.querySelector('[data-heat-select]');
            if (sel) { sel.value = opener.dataset.heat; titleInfo(dlg); }
        }
    });
    // With a heat picker, the title shows the picked heat's line, grade and target CE.
    function titleInfo(dlg) {
        var sel = dlg.querySelector('[data-heat-select]'), out = dlg.querySelector('[data-heat-info]'), opt = sel && sel.selectedOptions[0];
        if (out) out.textContent = opt && opt.value ? opt.dataset.info || '' : '';
    }
    document.addEventListener('change', function (e) { var sel = e.target.closest && e.target.closest('[data-heat-select]'); if (sel) titleInfo(sel.closest('dialog')); });
    document.addEventListener('DOMContentLoaded', function () { document.querySelectorAll('dialog [data-heat-select]').forEach(function (s) { titleInfo(s.closest('dialog')); }); });
})();
</script>
@endPushOnce
