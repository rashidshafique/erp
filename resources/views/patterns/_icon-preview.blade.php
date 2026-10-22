{{-- A pictogram beside a pattern type or mould arrangement dropdown that changes with the choice. $for: the select's id; $kind:
     'type' or 'arrangement'. Include once per select; the icons and the script are added to the page once. --}}
@php($icons = \App\Support\MouldIcons::all())
<span class="mi-preview" data-mi-for="{{ $for }}" data-mi-kind="{{ $kind }}" aria-hidden="true"></span>
@pushOnce('dialogs', 'mould-icon-preview')
<style>
    .mi-preview { display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; flex: none; border: 1px solid var(--border); border-radius: 8px; color: var(--accent); background: var(--surface); }
    .mi-field { display: flex; gap: 8px; align-items: center; }
    .mi-field select { flex: 1; min-width: 0; }
    .mi { vertical-align: -4px; flex: none; }
</style>
<script>
(function () {
    var icons = @json($icons);
    function show(span) {
        var sel = document.getElementById(span.dataset.miFor);
        if (sel) span.innerHTML = (icons[span.dataset.miKind] || {})[sel.value] || '';
    }
    function all() { document.querySelectorAll('[data-mi-for]').forEach(show); }
    document.addEventListener('change', function (e) {
        if (e.target.id) document.querySelectorAll('[data-mi-for="' + e.target.id + '"]').forEach(show);
    });
    document.addEventListener('DOMContentLoaded', all);
    // A dialog is built before the page's own script runs, so its previews are filled when it opens too.
    document.addEventListener('click', function (e) { if (e.target.closest('[data-quick]')) setTimeout(all, 0); });
})();
</script>
@endPushOnce
