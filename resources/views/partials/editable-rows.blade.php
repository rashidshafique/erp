{{-- Read-only table rows that turn into inputs when "Edit" is pressed.
     Markup per row:  <tr data-editable-row> ... <span class="ro">text</span><span class="ed"><input form="f-1" ...></span> ...
     Buttons: [data-row-edit] opens the row, [data-row-cancel form="f-1"] resets and closes it. One row is open at a time,
     unless the rows sit inside [data-editable-many]. A cancel button without form="…" puts back only that row's own inputs,
     for a grid whose rows share one form. Include this partial once per page. --}}
<style>
    [data-editable-row] .ed { display: none; }
    [data-editable-row].is-editing .ed { display: block; }
    [data-editable-row].is-editing .ed-act { display: inline-flex; gap: 6px; }
    [data-editable-row].is-editing .ro { display: none; }
    [data-editable-row] .ro.ro-act { display: inline-flex; gap: 6px; align-items: center; }
    [data-editable-row].is-editing { background: color-mix(in srgb, var(--accent) 6%, #fff); }
    [data-editable-row] td { vertical-align: top; }
    [data-editable-row] .ed input:not([type=checkbox]), [data-editable-row] .ed select { min-width: 0; max-width: 100%; }
    [data-editable-row] .ed input[type=checkbox] { width: auto; min-height: 0; }
    /* A long description is cut with an ellipsis (full text in the tooltip) so the table never outgrows the screen. */
    .col-desc { width: 220px; max-width: 220px; }
    [data-editable-row]:not(.is-editing) .ro.clip { display: block; max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    [data-editable-row] .ro:not(.clip) { overflow-wrap: anywhere; }
</style>
<script>
(function () {
    function close(tr) {
        if (!tr) return;
        var f = tr.querySelector('[data-row-cancel]');
        var form = f && f.getAttribute('form') && document.getElementById(f.getAttribute('form'));
        if (form) {
            form.reset();
        } else {
            tr.querySelectorAll('input, select, textarea').forEach(function (c) {
                if (c.type === 'checkbox' || c.type === 'radio') c.checked = c.defaultChecked;
                else if (c.tagName === 'SELECT') [].forEach.call(c.options, function (o) { o.selected = o.defaultSelected; });
                else c.value = c.defaultValue;
            });
        }
        tr.classList.remove('is-editing');
    }
    document.addEventListener('click', function (e) {
        var edit = e.target.closest('[data-row-edit]');
        if (edit) {
            var tr = edit.closest('[data-editable-row]');
            if (!tr.closest('[data-editable-many]')) {
                document.querySelectorAll('[data-editable-row].is-editing').forEach(function (o) { if (o !== tr) close(o); });
            }
            tr.classList.add('is-editing');
            var first = tr.querySelector('.ed input:not([type=hidden]):not([type=checkbox]), .ed select');
            if (first) first.focus();
            return;
        }
        var cancel = e.target.closest('[data-row-cancel]');
        if (cancel) { e.preventDefault(); close(cancel.closest('[data-editable-row]')); }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        document.querySelectorAll('[data-editable-row].is-editing').forEach(function (o) { if (!o.closest('[data-editable-many]')) close(o); });
    });
})();
</script>
