{{-- The "now" buttons' script and the cup card styles of thermal._fields. Include once on any page that uses the fields. --}}
@pushOnce('dialogs', 'thermal-fields-assets')
<script>
document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-now]'); if (!b) return;
    var d = new Date(), p = function (n) { return String(n).padStart(2, '0'); };
    document.getElementById(b.dataset.now).value = d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate()) + 'T' + p(d.getHours()) + ':' + p(d.getMinutes());
});
</script>
<style>
    .cup-times { display:grid; grid-template-columns:1fr; gap:8px; margin-bottom:10px; }
    .now-row { display:flex; gap:6px; align-items:center; }
    .now-row input { flex:1; min-width:0; }
    .cup-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:16px; align-items:start; margin-bottom:16px; }
    .cup-grid .panel { margin-bottom:0; }
    .curve-box { margin-top:12px; border-top:1px solid #e6eaf0; padding-top:10px; }
    .curve-box summary { cursor:pointer; font-weight:600; font-size:13px; }
    .static-value { min-height: 34px; display: flex; align-items: center; font-weight: 600; gap: 6px; }
    @media (max-width: 1024px) { .cup-grid { grid-template-columns:1fr; } }
</style>
@endPushOnce
