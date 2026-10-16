
<style>
    tr.row-link { cursor: pointer; }
    tr.row-link:hover { background: #f1f7f3 !important; }
</style>
<script>
(function () {
    if (document.body.getAttribute('data-row-click') !== '1') return;
    var SKIP_TEXT = /^(edit|delete|remove|print|download|create|new|add|cancel|back|save|view complete|manage)/i;
    var SKIP_HREF = /(\/edit\b|\/create\b|\/print\b|\/download\b|\/remove\b|\/certificate\b|\/labels\/|^#|^javascript:|^mailto:|^tel:)/i;

    function target(tr) {
        if (tr.closest('table.no-row-click') || tr.closest('thead')) return null;
        if (tr.querySelector('input, select, textarea, button')) return null;   // data-entry rows keep their own controls
        var links = tr.querySelectorAll('a[href]');
        for (var i = 0; i < links.length; i++) {
            var a = links[i];
            var href = a.getAttribute('href') || '';
            if (a.target === '_blank' || SKIP_HREF.test(href) || SKIP_TEXT.test((a.textContent || '').trim())) continue;
            if (a.closest('form')) continue;
            return a;
        }
        return null;
    }

    document.querySelectorAll('.page-content table tbody tr').forEach(function (tr) {
        var a = target(tr);
        if (!a) return;
        tr.classList.add('row-link');
        tr.addEventListener('click', function (e) {
            if (e.target.closest('a, button, input, select, textarea, label, summary, form')) return;   // real controls win
            if (window.getSelection && String(window.getSelection())) return;                           // user is selecting text
            if (e.ctrlKey || e.metaKey || e.button === 1) { window.open(a.href, '_blank'); return; }
            window.location.href = a.href;
        });
    });
})();
</script>
<?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/partials/rowlink.blade.php ENDPATH**/ ?>