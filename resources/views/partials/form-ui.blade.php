{{-- Roomy, uncluttered forms. Controls keep a sensible width instead of stretching across the page, fields sit in fixed-width columns with
     generous spacing, and a grid marked data-progressive tucks its blank optional fields behind "Show more" (Administration → Display). --}}
<style>
    /* Layout: fixed-width columns that never stretch, with room to breathe. */
    .form-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 320px)); gap: 24px 36px; justify-content: start; align-items: start; }
    .form-grid .field-full { grid-column: 1 / -1; }
    .panel-body:has(> .form-grid), .panel-body:has(> form > .form-grid) { padding: 24px 28px 28px; }
    .panel-header:has(+ .panel-body > .form-grid), .panel-header:has(+ .panel-body > form > .form-grid) { padding: 14px 28px; }
    .panel-header:has(+ .panel-body > .form-grid) h2 { font-size: 15px; }

    /* Labels and help text. */
    .field > label, .field > .label { display: block; margin: 0 0 8px; font-size: 13px; font-weight: 600; color: #33414a; letter-spacing: 0; }
    .field .help { margin-top: 7px; font-size: 12px; line-height: 1.45; color: var(--muted); max-width: 46ch; }
    .field .error-text { margin-top: 7px; font-size: 12.5px; }
    .field > label .required { margin-left: 2px; }

    /* Controls: comfortable height, soft edges, and never wider than their content needs. */
    .field input:not([type=checkbox]):not([type=radio]):not([type=color]):not([type=range]):not([type=file]),
    .field select, .field textarea {
        min-height: 42px; padding: 10px 13px; border: 1px solid #cdd6db; border-radius: 9px; background: #fff; font-size: 14px; width: 100%;
        transition: border-color .15s, box-shadow .15s;
    }
    .field textarea { min-height: 96px; line-height: 1.5; max-width: 640px; }
    .field input[type=text], .field input:not([type]), .field input[type=email], .field input[type=search], .field input[type=tel], .field input[type=url], .field input[type=password] { max-width: 340px; }
    .field-full input[type=text], .field-full input:not([type]), .field-full input[type=email], .field-full input[type=url] { max-width: 520px; }
    .field select { max-width: 300px; padding-right: 34px; }
    .field input[type=number] { max-width: 170px; }
    .field input[type=date], .field input[type=datetime-local], .field input[type=time], .field input[type=month] { max-width: 190px; }
    .field input[type=file] { max-width: 420px; font-size: 13px; }
    .field input:hover:not(:focus), .field select:hover:not(:focus), .field textarea:hover:not(:focus) { border-color: #b3bfc6; }
    .field input:focus, .field select:focus, .field textarea:focus { outline: 0; border-color: var(--accent); box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 18%, transparent); }
    .field input::placeholder, .field textarea::placeholder { color: #9aa6ad; }
    .field input[readonly], .field input:disabled, .field select:disabled { background: #f4f6f7; color: #6b7882; }
    .field input[type=checkbox], .field input[type=radio] { width: 17px; height: 17px; accent-color: var(--accent); }
    .field label:has(> input[type=checkbox]), .field label:has(> input[type=radio]) { display: flex; align-items: center; gap: 9px; font-weight: 500; margin: 0; }

    /* Inside tables, filters and toolbars the controls stay as they were. */
    td .field input, td .field select, td input, td select, td textarea { max-width: none; }
    .filters .field { flex: 0 1 auto; }
    .filters .field input, .filters .field select { min-height: 36px; padding: 7px 11px; border-radius: 8px; max-width: 240px; }
    .table-toolbar .field input:not(#_), .table-toolbar .field select:not(#_), .table-toolbar input:not(#_), .table-toolbar select:not(#_) { min-height: 26px; padding-block: 2px; border-radius: 6px; font-size: inherit; }
    .table-toolbar .tt-search input:not(#_) { padding-left: 27px; padding-right: 8px; }

    /* Whitespace between a form's panels and its buttons. */
    form > .panel + .panel { margin-top: 18px; }
    form > .actions, form > p:last-child { margin-top: 22px; }
    .panel-body > p.help, .panel-body > .muted.small { max-width: 70ch; }

    /* Optional fields tucked away. */
    .more-fields { display: contents; }
    .more-fields[hidden] { display: none; }
    .more-toggle { grid-column: 1 / -1; justify-self: start; margin-top: -4px; }
    .more-toggle button { appearance: none; border: 0; background: transparent; color: var(--accent); font: inherit; font-weight: 600; cursor: pointer; padding: 6px 0; display: inline-flex; gap: 8px; align-items: center; }
    .more-toggle button:hover { text-decoration: underline; }
    .more-toggle .chev { display: inline-block; width: 18px; height: 18px; border-radius: 50%; background: color-mix(in srgb, var(--accent) 12%, #fff); text-align: center; line-height: 17px; font-size: 14px; }

    @media (max-width: 700px) {
        .form-grid { grid-template-columns: 1fr; gap: 20px; }
        .panel-body:has(> .form-grid), .panel-body:has(> form > .form-grid) { padding: 18px; }
        .field input, .field select, .field textarea { max-width: none !important; }
    }
</style>
@if (\App\Support\UiSettings::all()['compact'])
<style>
    /* Compact density (the default): tight forms, many fields per row, small controls. */
    .form-grid { grid-template-columns: repeat(auto-fill, minmax(170px, 230px)); gap: 10px 16px; }
    .panel-body:has(> .form-grid), .panel-body:has(> form > .form-grid) { padding: 12px 16px 14px; }
    .panel-header:has(+ .panel-body > .form-grid), .panel-header:has(+ .panel-body > form > .form-grid) { padding: 8px 16px; }
    .panel-header:has(+ .panel-body > .form-grid) h2 { font-size: 13.5px; }
    .field > label, .field > .label { margin: 0 0 3px; font-size: 12px; }
    .field .help { margin-top: 3px; font-size: 11px; line-height: 1.35; max-width: 40ch; }
    .field .error-text { margin-top: 3px; font-size: 11.5px; }
    .field input:not([type=checkbox]):not([type=radio]):not([type=color]):not([type=range]):not([type=file]),
    .field select, .field textarea { min-height: 30px; padding: 5px 9px; border-radius: 6px; font-size: 13px; }
    .field textarea { min-height: 58px; line-height: 1.4; max-width: 520px; }
    .field input[type=text], .field input:not([type]), .field input[type=email], .field input[type=search], .field input[type=tel], .field input[type=url], .field input[type=password] { max-width: 260px; }
    .field-full input[type=text], .field-full input:not([type]), .field-full input[type=email], .field-full input[type=url] { max-width: 420px; }
    .field select { max-width: 230px; padding-right: 26px; }
    .field input[type=number] { max-width: 120px; }
    .field input[type=date], .field input[type=datetime-local], .field input[type=time], .field input[type=month] { max-width: 150px; }
    .field input:focus, .field select:focus, .field textarea:focus { box-shadow: 0 0 0 2px color-mix(in srgb, var(--accent) 18%, transparent); }
    .field input[type=checkbox], .field input[type=radio] { width: 15px; height: 15px; }
    .field label:has(> input[type=checkbox]), .field label:has(> input[type=radio]) { gap: 7px; }
    .filters .field input, .filters .field select { min-height: 30px; padding: 4px 9px; }
    form > .panel + .panel { margin-top: 10px; }
    form > .actions, form > p:last-child { margin-top: 12px; }
    .more-toggle { margin-top: -2px; }
    .more-toggle button { padding: 2px 0; font-size: 12.5px; }
    .more-toggle .chev { width: 15px; height: 15px; line-height: 14px; font-size: 12px; }
</style>
@endif
<script>
(function () {
    if (document.body.getAttribute('data-tidy-forms') !== '1') return;
    function isRequired(f) {
        if (f.querySelector('[required], .required')) return true;
        var l = f.querySelector('label'); return !!(l && /\*\s*$/.test(l.textContent));
    }
    function isBlank(f) {
        var ctl = f.querySelectorAll('input, select, textarea'); if (!ctl.length) return false;
        for (var i = 0; i < ctl.length; i++) {
            var c = ctl[i], t = (c.type || '').toLowerCase();
            if (t === 'hidden') continue;
            if (t === 'checkbox' || t === 'radio') { if (c.checked) return false; else continue; }
            if (c.tagName === 'SELECT') { if (c.value !== '' && c.selectedIndex > 0) return false; if (c.value !== '' && c.selectedIndex === 0 && c.options[0].value !== '') return false; continue; }
            if (c.value !== '' ) return false;
        }
        return true;
    }
    document.querySelectorAll('.form-grid[data-progressive]').forEach(function (grid) {
        var fields = [].slice.call(grid.children).filter(function (e) { return e.classList.contains('field'); });
        var hide = fields.filter(function (f) { return !isRequired(f) && !f.hasAttribute('data-keep') && !f.querySelector('.error-text') && isBlank(f); });
        var min = parseInt(grid.getAttribute('data-progressive'), 10) || 3;
        if (fields.length < 6 || hide.length < min) return;
        var wrap = document.createElement('div'); wrap.className = 'more-fields'; wrap.hidden = true;
        hide.forEach(function (f) { wrap.appendChild(f); });
        var bar = document.createElement('div'); bar.className = 'more-toggle';
        var btn = document.createElement('button'); btn.type = 'button'; btn.setAttribute('aria-expanded', 'false');
        function label(open) { btn.innerHTML = '<span class="chev">' + (open ? '&minus;' : '+') + '</span>' + (open ? 'Hide the optional fields' : 'Show ' + hide.length + ' more optional field' + (hide.length === 1 ? '' : 's')); btn.setAttribute('aria-expanded', open ? 'true' : 'false'); }
        label(false);
        btn.addEventListener('click', function () { wrap.hidden = !wrap.hidden; label(!wrap.hidden); });
        bar.appendChild(btn);
        grid.appendChild(bar); grid.appendChild(wrap);
        // A browser validation message on a hidden field must still be visible.
        grid.closest('form') && grid.closest('form').addEventListener('invalid', function (e) { if (wrap.contains(e.target)) { wrap.hidden = false; label(true); } }, true);
    });
})();
</script>
