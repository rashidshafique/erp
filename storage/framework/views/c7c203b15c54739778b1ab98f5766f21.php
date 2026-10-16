
<style>
    .ico { width: 14px; height: 14px; flex: none; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; vertical-align: -2px; }
    .btn .ico { margin-right: 2px; }
    .table-toolbar { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; margin: 0 0 0 auto; padding: 3px; border: 1px solid var(--border); border-radius: 8px; background: var(--page); }
    .table-toolbar.is-filtered { border-color: var(--accent); box-shadow: 0 0 0 2px color-mix(in srgb, var(--accent) 14%, transparent); }
    .table-toolbar .field, .table-toolbar > label { flex: none !important; margin: 0; min-width: 0; }
    .table-toolbar .sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }
    .table-toolbar input, .table-toolbar select { width: auto; min-width: 0; min-height: 26px; padding: 2px 8px; font-size: 12.5px; background: #fff; }
    .table-toolbar input[type=date] { padding: 1px 6px; }
    .table-toolbar .tt-search { position: relative; display: inline-flex; align-items: center; }
    .table-toolbar .tt-search .ico { position: absolute; left: 8px; color: var(--muted); pointer-events: none; }
    .table-toolbar .tt-search input { width: 190px; padding-left: 27px; }
    .table-toolbar .btn { min-height: 26px; padding: 2px 8px; }
    .table-toolbar .btn-clear { color: var(--muted); }
    .table-toolbar.is-filtered .btn-clear { color: var(--danger); }
    @media (max-width: 700px) { .table-toolbar { width: 100%; margin-left: 0; } .table-toolbar .tt-search, .table-toolbar .tt-search input { width: 100%; } }
</style>
<script>
(function () {
    var ICONS = {
        search: '<path d="M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16zM21 21l-4.3-4.3"/>',
        filter: '<path d="M22 3H2l8 9.5V19l4 2v-8.5L22 3z"/>',
        clear: '<path d="M18 6 6 18M6 6l12 12"/>',
        plus: '<path d="M12 5v14M5 12h14"/>'
    };
    function icon(name) { return '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true">' + ICONS[name] + '</svg>'; }

    // "+ New …" buttons get a real plus icon.
    document.querySelectorAll('.btn').forEach(function (b) {
        var t = b.firstChild;
        if (t && t.nodeType === 3 && /^\s*\+\s/.test(t.nodeValue)) { t.nodeValue = t.nodeValue.replace(/^\s*\+\s*/, ' '); b.insertAdjacentHTML('afterbegin', icon('plus')); }
    });

    if (document.body.getAttribute('data-toolbar') !== '1') return;
    function pageTitle() { var h = document.querySelector('.page-heading h1, h1'); return h ? h.textContent.trim() : 'Results'; }

    document.querySelectorAll('.page-content form').forEach(function (form) {
        if ((form.getAttribute('method') || '').toLowerCase() !== 'get' || form.hasAttribute('data-no-toolbar')) return;
        var own = form.closest('.panel');
        if (!own || form.closest('table')) return;
        var table = own.querySelector('table'), header, remove = null;
        var results = function (el) { return el && el.classList && el.classList.contains('panel') && (el.querySelector('table, .empty-state, .table-wrap')); };

        if (table) {
            // The filter sits inside the results panel, above its table: use that panel's header.
            if (!(form.compareDocumentPosition(table) & Node.DOCUMENT_POSITION_FOLLOWING)) return;
            header = own.querySelector(':scope > .panel-header');
            if (!header) { header = document.createElement('div'); header.className = 'panel-header'; header.innerHTML = '<h2></h2>'; header.firstChild.textContent = pageTitle(); own.insertBefore(header, own.firstChild); }
            var bodyOf = form.parentNode;
            if (bodyOf !== own && bodyOf !== header && bodyOf.children.length === 1) remove = bodyOf;
        } else {
            // A panel of its own (with or without a title), followed by the results panel.
            var target = own.nextElementSibling, hops = 0;
            while (target && !results(target) && hops < 3) { target = target.nextElementSibling; hops++; }
            if (!results(target)) return;
            header = target.querySelector(':scope > .panel-header');
            if (!header) { header = document.createElement('div'); header.className = 'panel-header'; header.innerHTML = '<h2></h2>'; header.firstChild.textContent = pageTitle(); target.insertBefore(header, target.firstChild); }
            remove = own;
        }

        form.classList.add('table-toolbar');
        form.classList.remove('filters');
        var filled = false;

        // Labels become hints inside the controls.
        form.querySelectorAll('.field').forEach(function (f) {
            var label = f.querySelector('label'), ctl = f.querySelector('input, select, textarea');
            if (!label || !ctl) return;
            var text = label.textContent.replace('*', '').trim();
            ctl.setAttribute('title', text); ctl.setAttribute('aria-label', text);
            if (ctl.tagName === 'INPUT' && /^(text|search|)$/.test(ctl.type) && !ctl.placeholder) ctl.placeholder = text;
            label.classList.add('sr-only');
        });

        form.querySelectorAll('input:not([type=hidden]):not([type=checkbox]):not([type=radio]), select').forEach(function (c) {
            if (c.value) filled = true;
            if (c.tagName === 'INPUT' && /^(text|search|)$/.test(c.type)) {
                var wrap = document.createElement('span'); wrap.className = 'tt-search';
                c.parentNode.insertBefore(wrap, c); wrap.insertAdjacentHTML('beforeend', icon('search')); wrap.appendChild(c);
            }
            if (c.tagName === 'SELECT' || c.type === 'date') c.addEventListener('change', function () { form.submit(); });   // filters act on the table straight away
        });

        var submit = form.querySelector('button[type=submit], button:not([type])');
        if (submit) { submit.innerHTML = icon('filter'); submit.setAttribute('title', 'Apply filters'); submit.setAttribute('aria-label', 'Apply filters'); submit.classList.add('btn-icon'); }
        var reset = Array.prototype.find.call(form.querySelectorAll('a'), function (a) { return /reset|clear/i.test(a.textContent); });
        if (reset) { reset.innerHTML = icon('clear'); reset.setAttribute('title', 'Clear filters'); reset.setAttribute('aria-label', 'Clear filters'); reset.classList.add('btn', 'btn-clear'); }
        else if (filled) {
            var a = document.createElement('a'); a.className = 'btn btn-clear'; a.href = location.pathname; a.title = 'Clear filters'; a.innerHTML = icon('clear'); form.appendChild(a);
        }
        if (filled) form.classList.add('is-filtered');

        header.appendChild(form);
        if (remove && !remove.querySelector('input, select, button, table, a')) remove.remove();
    });
})();
</script>
<?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/partials/table-ui.blade.php ENDPATH**/ ?>