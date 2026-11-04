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
