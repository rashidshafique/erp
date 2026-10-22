{{-- The script and styles every native <dialog class="qd"> on a page shares: opening by [data-quick="id"], closing, and reopening
     after a validation error. Included by partials.quick-dialog and by any other dialog built on it. --}}
@pushOnce('dialogs', 'quick-dialog-assets')
<style>
.qd { width: min(520px, calc(100vw - 32px)); padding: 0; border: 0; border-radius: 12px; background: var(--surface); color: var(--text); box-shadow: 0 24px 60px rgb(15 23 42 / 28%); }
.qd::backdrop { background: rgb(15 23 42 / 45%); }
.qd-head { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-bottom: 1px solid var(--border); }
.qd-head h2 { margin: 0; font-size: 15px; }
.qd-x { border: 0; background: transparent; font-size: 20px; line-height: 1; color: var(--muted); cursor: pointer; }
.qd-body { padding: 12px 16px 4px; max-height: calc(100vh - 160px); overflow-y: auto; }
.qd-errors { margin: 0 0 10px; }
.qd-fields { display: grid; grid-template-columns: 1fr 1fr; gap: 10px 14px; }
.qd-fields .field-full { grid-column: 1 / -1; }
.qd-fields .field input, .qd-fields .field select, .qd-fields .field textarea { max-width: 100%; }
.qd [aria-invalid=true] { border-color: var(--danger) !important; }
.qd-foot { display: flex; justify-content: flex-end; gap: 8px; padding: 12px 16px 14px; }
.qd-add { margin-left: 6px; font-size: 12px; font-weight: 600; color: var(--accent); white-space: nowrap; }
@media (max-width: 768px) { .qd-fields { grid-template-columns: 1fr; } }
</style>
<script>
(function () {
    function showErrors(dlg, errors) {
        var box = dlg.querySelector('.qd-errors'), keys = Object.keys(errors || {});
        dlg.querySelectorAll('[aria-invalid]').forEach(function (f) { f.removeAttribute('aria-invalid'); });
        box.innerHTML = '';
        box.hidden = keys.length === 0;
        keys.forEach(function (k) {
            [].concat(errors[k]).forEach(function (m) { var d = document.createElement('div'); d.textContent = m; box.appendChild(d); });
            var field = dlg.querySelector('[name="' + k + '"]');
            if (field) field.setAttribute('aria-invalid', 'true');
        });
    }
    function open(dlg, link) {
        dlg.dataset.target = (link && link.dataset.quickTarget) || '';
        dlg.dataset.value = (link && link.dataset.quickValue) || 'id';
        if (dlg.showModal) { if (!dlg.open) dlg.showModal(); } else { dlg.setAttribute('open', ''); }
        var first = dlg.querySelector('.qd-fields input:not([type=hidden]), .qd-fields select, .qd-fields textarea');
        if (first) setTimeout(function () { first.focus(); }, 30);
    }
    function addOption(target, item, valueKey) {
        var value = valueKey === 'label' ? item.label : item.id, group = target.dataset.quickList;
        var selects = group ? document.querySelectorAll('select[data-quick-list="' + group + '"]') : [target];
        selects.forEach(function (s) {
            if (![].some.call(s.options, function (o) { return o.value === value; })) s.add(new Option(item.label, value));
        });
        target.value = value;
        target.dispatchEvent(new Event('change', { bubbles: true }));
    }
    document.addEventListener('click', function (e) {
        var link = e.target.closest('[data-quick]');
        if (link) {
            var dlg = document.getElementById(link.dataset.quick);
            if (dlg) { e.preventDefault(); open(dlg, link); }
            return;
        }
        var d = e.target.closest('dialog.qd');
        if (d && (e.target === d || e.target.closest('[data-close]'))) d.close();
    });
    document.addEventListener('submit', function (e) {
        var form = e.target, dlg = form.closest('dialog.qd'), target = dlg && document.getElementById(dlg.dataset.target);
        if (!target || !window.fetch) return;
        e.preventDefault();
        var btn = form.querySelector('[type=submit]');
        btn.disabled = true;
        fetch(form.action, { method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json().catch(function () { return {}; }).then(function (body) { return { ok: r.ok, body: body }; }); })
            .then(function (res) {
                if (!res.ok) { showErrors(dlg, res.body.errors || { form: [res.body.message || 'It could not be saved. Try again.'] }); return; }
                addOption(target, res.body, dlg.dataset.value);
                form.reset(); showErrors(dlg, {}); dlg.close();
            })
            .catch(function () { showErrors(dlg, { form: ['The server could not be reached. Try again.'] }); })
            .then(function () { btn.disabled = false; });
    });
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('dialog.qd[data-reopen]').forEach(function (dlg) { open(dlg, null); showErrors(dlg, JSON.parse(dlg.dataset.reopen)); });
    });
})();
</script>
@endPushOnce
