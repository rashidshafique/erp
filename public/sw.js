/*
 * Foundry ERP Floor service worker: keeps the phone screens and the forms a tech has opened, so they open with no signal.
 * Pages are fetched from the network first and the last good copy is used only when the network fails.
 * Everything is dropped when the person signs out, so the next person on a shared phone sees none of it.
 */
var PAGES = 'foundry-pages-v1', ASSETS = 'foundry-assets-v1';

self.addEventListener('install', function (e) {
    e.waitUntil(caches.open(ASSETS).then(function (c) { return c.addAll(['/js/phone-queue.js', '/icons/icon-192.png']); }).then(function () { return self.skipWaiting(); }));
});
self.addEventListener('activate', function (e) { e.waitUntil(self.clients.claim()); });

self.addEventListener('message', function (e) {
    if (e.data === 'signed-out') { e.waitUntil(caches.delete(PAGES)); }
});

self.addEventListener('fetch', function (e) {
    var r = e.request, u = new URL(r.url);
    if (r.method !== 'GET' || u.origin !== location.origin || u.pathname === '/m/token') { return; }

    if (r.mode === 'navigate') {
        e.respondWith(fetch(r).then(function (res) {
            // Keep only successful pages that were not a redirect to the sign-in screen.
            if (res.ok && !res.redirected && !/\/login$/.test(new URL(res.url).pathname)) {
                var copy = res.clone();
                caches.open(PAGES).then(function (c) { return c.put(r, copy); });
            }
            return res;
        }).catch(function () {
            return caches.match(r).then(function (hit) { return hit || caches.match('/m'); }).then(function (hit) {
                return hit || new Response('<!doctype html><meta name=viewport content="width=device-width,initial-scale=1"><body style="font-family:system-ui;padding:24px"><h2>No signal</h2><p>Open this screen once with signal and it will be here next time.</p>', { headers: { 'Content-Type': 'text/html' } });
            });
        }));
        return;
    }

    if (u.pathname.startsWith('/js/') || u.pathname.startsWith('/css/') || u.pathname.startsWith('/icons/') || u.pathname.startsWith('/build/')) {
        e.respondWith(caches.open(ASSETS).then(function (c) {
            return c.match(r).then(function (hit) {
                var net = fetch(r).then(function (res) { if (res.ok) { c.put(r, res.clone()); } return res; });
                return hit || net;
            });
        }));
    }
});
