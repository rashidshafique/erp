
<style>
    .tabbar { display: flex; gap: 2px; flex-wrap: wrap; border-bottom: 2px solid var(--border); margin: 6px 0 18px; position: sticky; top: 0; background: var(--page); z-index: 5; padding-top: 4px; }
    .tabbar button { appearance: none; border: 0; background: transparent; padding: 10px 16px; font: inherit; font-weight: 600; color: var(--muted); cursor: pointer; border-bottom: 3px solid transparent; margin-bottom: -2px; border-radius: 6px 6px 0 0; }
    .tabbar button:hover { color: var(--text); background: var(--surface); }
    .tabbar button[aria-selected="true"] { color: var(--accent); border-bottom-color: var(--accent); background: var(--surface); }
    .tabbar button:focus-visible { outline: 2px solid var(--accent); outline-offset: -2px; }
    .tab-pane[hidden] { display: none; }
    @media print { .tabbar { display: none; } .tab-pane[hidden] { display: block; } }
</style>
<script>
(function () {
    var main = document.querySelector('.page-content');
    if (!main) return;
    var markers = main.querySelectorAll(':scope > .tab-marker');
    if (markers.length < 2) return;

    var kids = Array.prototype.slice.call(main.children);
    var first = kids.indexOf(markers[0]);
    var nav = document.createElement('div');
    nav.className = 'tabbar'; nav.setAttribute('role', 'tablist');
    var tabs = [], hoisted = [], current = null;

    kids.slice(first).forEach(function (el) {
        if (el.classList.contains('tab-marker')) {
            var name = el.getAttribute('data-tab-start');
            var pane = document.createElement('div');
            pane.className = 'tab-pane'; pane.setAttribute('role', 'tabpanel'); pane.hidden = true;
            var btn = document.createElement('button');
            btn.type = 'button'; btn.setAttribute('role', 'tab'); btn.textContent = name;
            current = { name: name, pane: pane, btn: btn, slug: name.toLowerCase().replace(/[^a-z0-9]+/g, '-') };
            tabs.push(current); nav.appendChild(btn);
            return;
        }
        if (el.classList.contains('alert')) { hoisted.push(el); return; }   // messages must never hide in another tab
        current.pane.appendChild(el);
    });

    var anchor = markers[0];
    hoisted.forEach(function (a) { main.insertBefore(a, anchor); });
    main.insertBefore(nav, anchor);
    tabs.forEach(function (t) { main.insertBefore(t.pane, anchor); });
    Array.prototype.forEach.call(markers, function (m) { m.remove(); });

    var key = 'erp-tab:' + location.pathname;
    function show(t, remember) {
        tabs.forEach(function (x) { var on = x === t; x.pane.hidden = !on; x.btn.setAttribute('aria-selected', on ? 'true' : 'false'); x.btn.tabIndex = on ? 0 : -1; });
        if (remember) { try { sessionStorage.setItem(key, t.slug); } catch (e) {} }
    }
    tabs.forEach(function (t, i) {
        t.btn.addEventListener('click', function () { show(t, true); });
        t.btn.addEventListener('keydown', function (e) {
            var n = e.key === 'ArrowRight' ? i + 1 : e.key === 'ArrowLeft' ? i - 1 : null;
            if (n === null) return;
            n = (n + tabs.length) % tabs.length; show(tabs[n], true); tabs[n].btn.focus();
        });
    });

    var start = tabs[0], h = decodeURIComponent(location.hash.replace('#', ''));
    var saved = null; try { saved = sessionStorage.getItem(key); } catch (e) {}
    tabs.forEach(function (t) { if (saved && t.slug === saved) start = t; });
    if (h) {
        tabs.forEach(function (t) {
            if (t.slug === h) start = t;
            else { try { if (t.pane.querySelector('#' + CSS.escape(h))) start = t; } catch (e) {} }
        });
    }
    show(start, false);
})();
</script>
<?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/partials/tabs.blade.php ENDPATH**/ ?>