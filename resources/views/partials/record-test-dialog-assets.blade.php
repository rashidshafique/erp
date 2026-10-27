{{-- The styles and tab script of the record-test dialog, shared with the read-only sample dialogs on the heat page. --}}
@pushOnce('dialogs', 'record-test-dialog-assets')
<style>
    dialog.qd.qd-wide { width: min(1200px, calc(100vw - 32px)); }
    .qd-tabs { display: flex; gap: 2px; padding: 0 16px; border-bottom: 1px solid var(--border); }
    .qd-tabs button { appearance: none; border: 0; background: transparent; padding: 10px 14px; font: inherit; font-weight: 600; color: var(--muted); cursor: pointer; border-bottom: 3px solid transparent; margin-bottom: -1px; }
    .qd-tab-actions { margin-left: auto; display: flex; align-items: center; gap: 12px; padding-bottom: 4px; }
    .qd-tab-actions[hidden] { display: none; }
    .qd-head .strip-device { margin: 0 12px 0 auto; display: flex; align-items: center; gap: 8px; }
    .qd-head .strip-device label { margin: 0; font-size: 12px; color: var(--muted); }
    .qd-head .strip-device select { min-width: 240px; min-height: 30px; padding: 3px 8px; font-size: 13px; }
    .qd-tabs .link-btn { padding: 0; min-height: 0; border: 0; }
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
        dlg.querySelectorAll('[data-rt-actions]').forEach(function (a) { a.hidden = tab !== 'thermal'; });
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
