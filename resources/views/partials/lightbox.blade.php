{{-- Photos open large over the page instead of in a new tab. Any link with data-lightbox="group" pointing at an image opens
     it; links sharing a group can be stepped through with the arrows (keys ← → or the buttons). Escape, × or a click on the
     dark background closes it. data-caption sets the caption. Included once, in the layout. --}}
<style>
    .lb { position: fixed; inset: 0; z-index: 90; display: none; align-items: center; justify-content: center; background: rgb(10 14 20 / 86%); }
    .lb.open { display: flex; }
    .lb figure { margin: 0; display: flex; flex-direction: column; align-items: center; gap: 10px; max-width: calc(100vw - 120px); max-height: calc(100vh - 60px); }
    .lb img { max-width: 100%; max-height: calc(100vh - 110px); object-fit: contain; border-radius: 6px; background: var(--surface); box-shadow: 0 20px 60px rgb(0 0 0 / 45%); padding: 0; min-height: 0; }
    .lb figcaption { color: rgb(255 255 255 / 88%); font-size: 13px; text-align: center; }
    .lb figcaption .n { opacity: .65; margin-left: 8px; }
    .lb button { position: absolute; border: 0; background: rgb(255 255 255 / 12%); color: var(--surface); width: 44px; height: 44px; border-radius: 50%; font-size: 24px; line-height: 1; cursor: pointer; display: flex; align-items: center; justify-content: center; }
    .lb button:hover { background: rgb(255 255 255 / 24%); }
    .lb button:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
    .lb .lb-x { top: 16px; right: 16px; }
    .lb .lb-prev { left: 16px; top: 50%; transform: translateY(-50%); }
    .lb .lb-next { right: 16px; top: 50%; transform: translateY(-50%); }
    .lb button[hidden] { display: none; }
    @media (max-width: 700px) { .lb figure { max-width: calc(100vw - 24px); } .lb .lb-prev, .lb .lb-next { top: auto; bottom: 16px; transform: none; } }
</style>
<div class="lb" id="lightbox" role="dialog" aria-modal="true" aria-label="Photo">
    <button type="button" class="lb-x" aria-label="Close">&times;</button>
    <button type="button" class="lb-prev" aria-label="Previous photo">&#8249;</button>
    <figure><img alt=""><figcaption></figcaption></figure>
    <button type="button" class="lb-next" aria-label="Next photo">&#8250;</button>
</div>
<script>
(function () {
    var box = document.getElementById('lightbox'), img = box.querySelector('img'), cap = box.querySelector('figcaption');
    var prev = box.querySelector('.lb-prev'), next = box.querySelector('.lb-next'), items = [], at = 0, opener = null;
    function show(i) {
        at = (i + items.length) % items.length;
        var a = items[at], text = a.dataset.caption || a.getAttribute('title') || '';
        img.src = a.href; img.alt = text;
        cap.textContent = text;
        if (items.length > 1) { var n = document.createElement('span'); n.className = 'n'; n.textContent = (at + 1) + ' / ' + items.length; cap.appendChild(n); }
        prev.hidden = next.hidden = items.length < 2;
    }
    function open(a) {
        var group = a.dataset.lightbox;
        items = [].filter.call(document.querySelectorAll('a[data-lightbox]'), function (x) { return x.dataset.lightbox === group; })
            .filter(function (x, i, all) { return all.findIndex(function (y) { return y.href === x.href; }) === i; });
        opener = a; box.classList.add('open'); document.body.style.overflow = 'hidden';
        show(Math.max(0, items.findIndex(function (x) { return x.href === a.href; })));
        box.querySelector('.lb-x').focus();
    }
    function close() { box.classList.remove('open'); img.removeAttribute('src'); document.body.style.overflow = ''; if (opener) opener.focus(); }
    document.addEventListener('click', function (e) {
        var a = e.target.closest('a[data-lightbox]');
        if (a && !e.ctrlKey && !e.metaKey && !e.shiftKey) { e.preventDefault(); open(a); }
    });
    box.addEventListener('click', function (e) {
        if (e.target.closest('.lb-x') || e.target === box) close();
        else if (e.target.closest('.lb-prev')) show(at - 1);
        else if (e.target.closest('.lb-next')) show(at + 1);
    });
    document.addEventListener('keydown', function (e) {
        if (!box.classList.contains('open')) return;
        if (e.key === 'Escape') { e.preventDefault(); close(); }
        else if (e.key === 'ArrowLeft') show(at - 1);
        else if (e.key === 'ArrowRight') show(at + 1);
    });
})();
</script>
