/*
 * Records saved on the phone (design 26a): a form marked data-offline is stored in the phone's IndexedDB first
 * (text and photos alike), then sent to the same address when there is signal. Each record carries an id the server
 * uses to ignore a repeat, so a lost reply never saves it twice. A refused record (validation, no permission, signed out)
 * stays on the phone with the reason until it is fixed or discarded.
 */
(function () {
    var DB = 'foundry-phone', STORE = 'queue', busy = false, listeners = [];

    function open() {
        return new Promise(function (ok, fail) {
            var r = indexedDB.open(DB, 1);
            r.onupgradeneeded = function () { r.result.createObjectStore(STORE, { keyPath: 'id' }); };
            r.onsuccess = function () { ok(r.result); };
            r.onerror = function () { fail(r.error); };
        });
    }
    function tx(mode, fn) {
        return open().then(function (db) {
            return new Promise(function (ok, fail) {
                var t = db.transaction(STORE, mode), res = fn(t.objectStore(STORE));
                t.oncomplete = function () { ok(res && res.result); };
                t.onerror = function () { fail(t.error); };
            });
        });
    }
    function all() { return tx('readonly', function (s) { return s.getAll(); }).then(function (r) { return (r || []).sort(function (a, b) { return a.saved - b.saved; }); }); }
    function put(rec) { return tx('readwrite', function (s) { return s.put(rec); }); }
    function drop(id) { return tx('readwrite', function (s) { return s.delete(id); }); }
    function changed() { all().then(function (rows) { listeners.forEach(function (f) { f(rows); }); }); }

    /** Only a browser that has opened the phone screens saves first; people at a desk keep the ordinary forms. */
    function onPhone() { try { return localStorage.getItem('erp.phone') === '1'; } catch (e) { return false; } }

    function uuid() {
        return (crypto.randomUUID && crypto.randomUUID()) || 'q' + Date.now() + Math.random().toString(16).slice(2);
    }

    function save(form) {
        var fields = [];
        new FormData(form).forEach(function (v, k) {
            if (v instanceof File && v.size === 0) { return; }
            fields.push([k, v]);
        });
        var rec = { id: uuid(), url: form.getAttribute('action'), label: form.dataset.offlineLabel || document.title, fields: fields, saved: Date.now(), state: 'waiting', note: '' };
        return put(rec).then(function () { changed(); return rec; });
    }

    function token() {
        return fetch('/m/token', { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then(function (r) {
            if (r.status === 401 || r.redirected) { throw { signin: true }; }
            return r.json();
        }).then(function (j) { return j.token; });
    }

    function sendOne(rec) {
        return token().then(function (t) {
            var body = new FormData();
            rec.fields.forEach(function (f) { body.append(f[0], f[1]); });
            body.set('_token', t);
            return fetch(rec.url, { method: 'POST', body: body, credentials: 'same-origin', redirect: 'manual', headers: { Accept: 'application/json', 'X-Queue-Id': rec.id } });
        }).then(function (r) {
            if (r.ok || r.type === 'opaqueredirect') { return drop(rec.id).then(function () { return true; }); }
            if (r.status === 401 || r.status === 419) { throw { signin: true }; }
            return r.json().catch(function () { return {}; }).then(function (j) {
                var first = j.errors ? Object.values(j.errors)[0][0] : null;
                rec.state = 'refused';
                rec.note = first || j.message || ('Refused (' + r.status + ')');
                return put(rec).then(function () { return false; });
            });
        });
    }

    /** Sends every waiting record, oldest first; stops at the first one that cannot reach the server. */
    function flush() {
        if (busy || !navigator.onLine) { return Promise.resolve(); }
        busy = true;
        return all().then(function (rows) {
            return rows.filter(function (r) { return r.state !== 'refused'; }).reduce(function (p, rec) {
                return p.then(function () { return sendOne(rec); });
            }, Promise.resolve());
        }).catch(function (e) {
            window.phoneQueueNeedsSignIn = !!(e && e.signin);
        }).then(function () { busy = false; changed(); });
    }

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form.hasAttribute || !form.hasAttribute('data-offline') || !window.indexedDB || !onPhone()) { return; }
        e.preventDefault();
        var btn = form.querySelector('[type=submit]');
        if (btn) { btn.disabled = true; }
        save(form).then(function () { return flush(); }).then(function () {
            window.location = form.dataset.offlineDone || '/m';
        }).catch(function () {
            if (btn) { btn.disabled = false; }
            form.removeAttribute('data-offline');
            form.submit();   // the phone cannot store it; send it the ordinary way
        });
    }, true);

    window.addEventListener('online', flush);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) { flush(); } });

    window.phoneQueue = {
        all: all, flush: flush, discard: function (id) { return drop(id).then(changed); },
        retry: function (id) { return all().then(function (rows) { var r = rows.filter(function (x) { return x.id === id; })[0]; if (r) { r.state = 'waiting'; r.note = ''; return put(r); } }).then(flush); },
        onChange: function (f) { listeners.push(f); changed(); },
    };
    flush();
})();

/*
 * Resizable right panel: any two-column grid whose right column is a fixed side panel (the 340px detail and context
 * panels on lists, records and dashboards) gets a drag edge on that panel's left side, like the menu resizer.
 * The width stays between 200 and 350px, is kept per page address in this browser, and a double-click resets it.
 * Narrow screens are left alone, so pages still drop to one column where their own styles say so.
 */
(function () {
    var MIN = 200, MAX = 350, WIDE = 1001, timer = null, busy = false;
    var clamp = function (w) { return Math.max(MIN, Math.min(MAX, Math.round(w))); };
    var key = function () { return 'erp.sideWidth:' + location.pathname; };
    var stored = function () { try { var w = parseInt(localStorage.getItem(key()), 10); return w >= MIN && w <= MAX ? w : null; } catch (e) { return null; } };
    var store = function (w) { try { if (w === null) { localStorage.removeItem(key()); } else { localStorage.setItem(key(), String(w)); } } catch (e) {} };

    var css = document.createElement('style');
    css.textContent = '.side-resizer{position:absolute;top:0;bottom:0;width:9px;z-index:5;cursor:col-resize;touch-action:none}'
        + '.side-resizer::after{content:"";position:absolute;top:0;bottom:0;left:4px;width:2px;background:transparent;transition:background .12s}'
        + '.side-resizer:hover::after,.side-resizer:focus-visible::after,.side-resizer.dragging::after{background:var(--accent,#2563eb)}'
        + 'body.resizing-side{cursor:col-resize;user-select:none}';
    document.head.appendChild(css);

    function tracks(el) {
        var t = getComputedStyle(el).gridTemplateColumns.trim().split(/\s+/);
        return t.length === 2 && /px$/.test(t[0]) && /px$/.test(t[1]) ? [parseFloat(t[0]), parseFloat(t[1])] : null;
    }
    function isPanel(el) {
        if (el.children.length < 2 || el.closest('.sidebar, table') || getComputedStyle(el).display !== 'grid') { return false; }
        var t = tracks(el);
        return !!t && t[0] > t[1] && t[1] >= (el.dataset.sideResize ? MIN : 280) && t[1] <= (el.dataset.sideResize ? MAX : 380);
    }
    function place(el) {
        var h = el.querySelector(':scope > .side-resizer'), s = getComputedStyle(el), t = tracks(el);
        if (!h || !t) { return; }
        var gap = parseFloat(s.columnGap) || 0;
        h.style.left = (el.clientWidth - (parseFloat(s.paddingRight) || 0) - t[1] - gap / 2 - 4.5) + 'px';
    }
    function apply(el, w) {
        var v = 'minmax(0, 1fr) ' + w + 'px';
        if (el.style.gridTemplateColumns !== v) { el.style.setProperty('grid-template-columns', v, 'important'); }
        place(el);
    }
    function release(el) {
        el.style.removeProperty('grid-template-columns');
        var h = el.querySelector(':scope > .side-resizer'); if (h) { h.remove(); }
        delete el.dataset.sideResize;
    }
    function handle(el) {
        var h = document.createElement('div');
        h.className = 'side-resizer'; h.tabIndex = 0;
        h.setAttribute('role', 'separator'); h.setAttribute('aria-orientation', 'vertical');
        h.setAttribute('aria-label', 'Drag to resize the side panel (double-click to reset)');
        if (getComputedStyle(el).position === 'static') { el.style.position = 'relative'; }
        h.addEventListener('pointerdown', function (e) {
            var x = e.clientX, w = tracks(el)[1];
            h.setPointerCapture(e.pointerId); h.classList.add('dragging'); document.body.classList.add('resizing-side');
            function move(ev) { apply(el, clamp(w + x - ev.clientX)); }
            function up() {
                h.removeEventListener('pointermove', move); h.removeEventListener('pointerup', up); h.removeEventListener('pointercancel', up);
                h.classList.remove('dragging'); document.body.classList.remove('resizing-side');
                store(tracks(el)[1]); scan();
            }
            h.addEventListener('pointermove', move); h.addEventListener('pointerup', up); h.addEventListener('pointercancel', up);
            e.preventDefault();
        });
        h.addEventListener('keydown', function (e) {
            var step = e.key === 'ArrowLeft' ? 10 : e.key === 'ArrowRight' ? -10 : 0;
            if (!step) { return; }
            var w = clamp(tracks(el)[1] + step); apply(el, w); store(w); e.preventDefault();
        });
        h.addEventListener('dblclick', function () { store(null); release(el); scan(); });
        el.appendChild(h);
    }
    function scan() {
        if (busy) { return; }
        busy = true;
        var wide = window.innerWidth >= WIDE;
        document.querySelectorAll('[data-side-resize]').forEach(function (el) {
            if (!wide || !el.isConnected) { release(el); return; }
            el.style.removeProperty('grid-template-columns');
            if (!isPanel(el)) { release(el); }
        });
        if (wide) {
            document.querySelectorAll('.main-area *').forEach(function (el) {
                if (!isPanel(el)) { return; }
                el.dataset.sideResize = '1';
                if (!el.querySelector(':scope > .side-resizer')) { handle(el); }
                apply(el, clamp(stored() || tracks(el)[1]));
            });
        }
        busy = false;
    }
    function later() { clearTimeout(timer); timer = setTimeout(scan, 150); }

    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', scan); } else { scan(); }
    window.addEventListener('resize', later);
    document.addEventListener('livewire:navigated', later);
    new MutationObserver(function (list) {
        if (busy || document.body.classList.contains('resizing-side')) { return; }
        for (var i = 0; i < list.length; i++) {
            var m = list[i];
            if (m.type === 'childList' && !(m.target.classList && m.target.classList.contains('side-resizer'))) { later(); return; }
        }
    }).observe(document.body, { childList: true, subtree: true });
})();
