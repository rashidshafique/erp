{{-- Type-to-search for long dropdowns. Mark a <select data-search-select>; each <option> may carry data-sub (a second line, such
     as a name) and data-img (a small photo). Clicking the select or typing on it opens a search box listing the options; picking
     one sets the select and fires "change", so the form and other scripts see an ordinary select. Without JavaScript the plain
     select still works. Works for selects added to the page later and inside a modal <dialog>. Include once per page. --}}
@once
<style>
    .ss-pop { position: fixed; z-index: 60; width: 300px; max-width: calc(100vw - 16px); background: var(--surface); border: 1px solid var(--border); border-radius: 8px; box-shadow: 0 12px 32px rgb(15 23 42 / 18%); padding: 6px; }
    .ss-pop input { width: 100%; min-height: 30px; padding: 4px 8px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px; }
    .ss-pop input:focus { outline: 0; border-color: var(--accent); box-shadow: 0 0 0 2px color-mix(in srgb, var(--accent) 18%, transparent); }
    .ss-pop ul { list-style: none; margin: 6px 0 0; padding: 0; max-height: 280px; overflow-y: auto; }
    .ss-pop li { display: flex; align-items: center; gap: 8px; padding: 4px 6px; border-radius: 6px; cursor: pointer; font-size: 12.5px; }
    .ss-pop li[aria-selected="true"] { background: color-mix(in srgb, var(--accent) 12%, transparent); }
    .ss-pop li img, .ss-pop li .ss-noimg { width: 34px; height: 34px; flex: none; border-radius: 5px; object-fit: cover; background: color-mix(in srgb, var(--border) 60%, transparent); }
    .ss-pop li span { display: flex; flex-direction: column; min-width: 0; line-height: 1.25; }
    .ss-pop li strong { font-weight: 600; }
    .ss-pop li small { color: var(--muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .ss-pop .ss-none { padding: 8px 6px; color: var(--muted); font-size: 12.5px; }
</style>
<script>
(function () {
    var pop = null, target = null, active = -1, items = [];
    function close(refocus) { if (pop) { pop.remove(); pop = null; } if (refocus && target) { target.focus(); } target = null; }
    function render(q) {
        var list = pop.querySelector('ul'), words = q.toLowerCase().split(/\s+/).filter(Boolean);
        list.innerHTML = '';
        items = [].filter.call(target.options, function (o) {
            if (o.disabled || o.parentNode.hidden) return false;
            var text = (o.text + ' ' + (o.dataset.sub || '')).toLowerCase();
            return words.every(function (w) { return text.indexOf(w) >= 0; });
        }).slice(0, 200);
        if (!items.length) { list.innerHTML = '<li class="ss-none" aria-disabled="true">Nothing matches.</li>'; active = -1; return; }
        items.forEach(function (o, i) {
            var li = document.createElement('li'); li.setAttribute('role', 'option'); li.dataset.i = i;
            if (o.dataset.img) { var img = document.createElement('img'); img.src = o.dataset.img; img.alt = ''; img.loading = 'lazy'; li.appendChild(img); }
            else if (target.dataset.searchSelect === 'photos') { var ph = document.createElement('i'); ph.className = 'ss-noimg'; li.appendChild(ph); }
            var t = document.createElement('span'), b = document.createElement('strong'); b.textContent = o.text; t.appendChild(b);
            if (o.dataset.sub) { var s = document.createElement('small'); s.textContent = o.dataset.sub; t.appendChild(s); }
            li.appendChild(t); list.appendChild(li);
        });
        var current = items.indexOf(target.options[target.selectedIndex]);
        mark(current >= 0 && !q ? current : 0);
    }
    // Below the select when the list fits there, otherwise above it. Above, the list is held by its bottom edge so it stays
    // against the select however short the filtered list gets.
    function place() {
        var r = target.getBoundingClientRect(), h = pop.offsetHeight, below = window.innerHeight - r.bottom - 8, above = r.top - 8;
        if (h + 4 > below && above > below) {
            pop.style.top = 'auto'; pop.style.bottom = (window.innerHeight - r.top + 4) + 'px';
        } else {
            pop.style.bottom = 'auto'; pop.style.top = (r.bottom + 4) + 'px';
        }
        pop.querySelector('ul').style.maxHeight = Math.max(120, Math.min(280, Math.max(below, above) - 60)) + 'px';
    }
    function mark(i) {
        var lis = pop.querySelectorAll('li[role=option]'); if (!lis.length) return;
        active = Math.max(0, Math.min(lis.length - 1, i));
        lis.forEach(function (li, k) { li.setAttribute('aria-selected', k === active ? 'true' : 'false'); });
        lis[active].scrollIntoView({ block: 'nearest' });
    }
    function pick(i) {
        var o = items[i]; if (!o) return;
        target.value = o.value; target.dispatchEvent(new Event('change', { bubbles: true }));
        close(true);
    }
    function open(select, typed) {
        close(false); target = select;
        pop = document.createElement('div'); pop.className = 'ss-pop'; pop.setAttribute('role', 'listbox');
        pop.innerHTML = '<input type="search" autocomplete="off" aria-label="Search"><ul></ul>';
        (select.closest('dialog') || document.body).appendChild(pop);
        pop.style.left = Math.max(8, Math.min(select.getBoundingClientRect().left, window.innerWidth - 308)) + 'px';
        var input = pop.querySelector('input'); input.value = typed || '';
        input.addEventListener('input', function () { render(input.value); });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown') { e.preventDefault(); mark(active + 1); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); mark(active - 1); }
            else if (e.key === 'Enter') { e.preventDefault(); pick(active); }
            else if (e.key === 'Escape') { e.preventDefault(); close(true); }
            else if (e.key === 'Tab') { close(true); }
        });
        pop.addEventListener('mousedown', function (e) { var li = e.target.closest('li[role=option]'); if (li) { e.preventDefault(); pick(+li.dataset.i); } });
        render(input.value); place(); input.focus();
    }
    document.addEventListener('mousedown', function (e) {
        var s = e.target.closest('select[data-search-select]');
        if (s && !s.disabled) { e.preventDefault(); if (target === s) close(true); else open(s); return; }
        if (pop && !pop.contains(e.target)) close(false);
    });
    document.addEventListener('keydown', function (e) {
        var s = e.target.closest && e.target.closest('select[data-search-select]');
        if (!s || pop || e.ctrlKey || e.metaKey || e.altKey && e.key !== 'ArrowDown') return;
        if (e.key === 'Enter' || e.key === ' ' || e.key === 'ArrowDown' || e.key.length === 1) { e.preventDefault(); open(s, e.key.length === 1 && e.key !== ' ' ? e.key : ''); }
    });
    window.addEventListener('resize', function () { if (pop) close(false); });
    document.addEventListener('scroll', function (e) { if (pop && !pop.contains(e.target)) close(false); }, true);
})();
</script>
@endonce
