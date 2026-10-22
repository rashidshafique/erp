{{-- The "now" buttons' script and the cup card styles of thermal._fields. Include once on any page that uses the fields. --}}
@pushOnce('dialogs', 'thermal-fields-assets')
<script>
document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-now]'); if (!b) return;
    var d = new Date(), p = function (n) { return String(n).padStart(2, '0'); };
    document.getElementById(b.dataset.now).value = d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate()) + 'T' + p(d.getHours()) + ':' + p(d.getMinutes());
});
function applyCup(t) {
    var root = t.closest('.tf-root'), card = root.querySelector('[data-cup="' + t.dataset.cupToggle + '"]');
    card.hidden = !t.checked;
    // A hidden cup sends nothing: its fields are switched off rather than left to be read as an empty card.
    card.querySelectorAll('input, select, textarea').forEach(function (i) { i.disabled = !t.checked; });
    root.querySelector('.cup-grid').classList.toggle('single', root.querySelectorAll('[data-cup]:not([hidden])').length < 2);
}
document.addEventListener('change', function (e) {
    var t = e.target.closest && e.target.closest('[data-cup-toggle]'); if (t) applyCup(t);
});
document.addEventListener('DOMContentLoaded', function () { document.querySelectorAll('[data-cup-toggle]').forEach(applyCup); });
</script>
<style>
    .tf-grid { display: grid; gap: 8px 12px; }
    .tf-cols-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    .tf-cols-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .tf-span2 { grid-column: span 2; }
    .tf .field { margin: 0; min-width: 0; }
    .tf .field > label { margin: 0 0 3px; font-size: 12px; }
    .tf input:not([type=checkbox]):not([type=file]), .tf select { min-height: 30px; padding: 3px 8px; font-size: 13px; max-width: 100%; width: 100%; }
    .tf .panel-header { padding: 7px 14px; }
    .tf .panel-header h2 { font-size: 14px; }
    .tf .panel-body { padding: 10px 14px 12px; }
    .tf .static-value { min-height: 30px; }
    .tf .now-row .btn-small { padding: 3px 8px; }
    .cup-toggles { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 20px; margin: 10px 0 8px; }
    .cup-toggles .check-field { display: inline-flex; align-items: center; gap: 6px; font-weight: 600; font-size: 13px; }
    .cup-toggles input { width: auto; min-height: 0; }
    .cup-grid.single { grid-template-columns: 1fr; }
    .cup-grid.single .tf-cols-3 { grid-template-columns: repeat(6, minmax(0, 1fr)); }
    @media (max-width: 900px) { .tf-cols-4, .tf-cols-3, .cup-grid.single .tf-cols-3 { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .cup-times { display:grid; grid-template-columns:repeat(auto-fit, minmax(190px, 1fr)); gap:8px 10px; margin-bottom:8px; }
    .lbl-row { display:flex; justify-content:space-between; align-items:baseline; margin-bottom:3px; }
    .lbl-row label { margin:0 !important; }
    .now-link { appearance:none; border:0; background:transparent; padding:0; font:inherit; font-size:12px; font-weight:600; color:var(--accent); cursor:pointer; }
    .now-link:hover { text-decoration:underline; }
    .now-row { display:flex; gap:6px; align-items:center; }
    .now-row input { flex:1; min-width:0; }
    .cup-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:16px; align-items:start; margin-bottom:16px; }
    .cup-grid .panel { margin-bottom:0; }
    .curve-box { margin-top:8px; border-top:1px solid #e6eaf0; padding-top:10px; }
    .curve-box summary { cursor:pointer; font-weight:600; font-size:13px; }
    .static-value { min-height: 34px; display: flex; align-items: center; font-weight: 600; gap: 6px; }
    @media (max-width: 1024px) { .cup-grid { grid-template-columns:1fr; } }
</style>
@endPushOnce
