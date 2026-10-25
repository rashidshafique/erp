{{-- The script and styles of thermal._fields: "now" buttons, cups on and off, live checks of each result tile against the heat's
     grade, and the cup card layout. Include once on any page that uses the fields. --}}
@pushOnce('dialogs', 'thermal-fields-assets')
<script>
(function () {
    var stamp = function () { var d = new Date(), p = function (n) { return String(n).padStart(2, '0'); };
        return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate()) + 'T' + p(d.getHours()) + ':' + p(d.getMinutes()); };

    function applyCup(t) {
        var root = t.closest('.tf-root');
        root.querySelectorAll('[data-cup="' + t.dataset.cupToggle + '"]').forEach(function (part) {
            part.hidden = !t.checked;
            // An unticked cup sends nothing: its fields are switched off, not sent as an empty card.
            part.querySelectorAll('input, select, textarea').forEach(function (i) { i.disabled = !t.checked; });
        });
        t.closest('.cup-card').classList.toggle('is-off', !t.checked);
        check(root);
    }

    // The heat's grade ranges: fixed on the form, or those of the heat chosen in the picker.
    function ranges(root) {
        var pick = root.querySelector('[data-heat-select]'), opt = pick && pick.selectedOptions[0];
        try { return JSON.parse((opt && opt.dataset.ranges) || root.dataset.ranges || '{}'); } catch (e) { return {}; }
    }
    var trim = function (v) { return v === null || v === undefined ? '…' : String(Number(v)); };

    function check(root) {
        var r = ranges(root), bad = 0, judged = 0;
        root.querySelectorAll('[data-spec-tile]').forEach(function (tile) {
            var key = tile.dataset.specTile, input = tile.querySelector('input'), badge = tile.querySelector('[data-badge]'), range = r[key];
            var hint = tile.querySelector('[data-range-for]');
            if (hint) hint.textContent = range ? trim(range[0]) + '–' + trim(range[1]) + '%' : 'No grade range';
            tile.classList.remove('is-ok', 'is-off');
            badge.textContent = '';
            if (!range || input.value === '' || input.disabled) return;
            var v = Number(input.value), low = range[0] !== null && v < Number(range[0]), high = range[1] !== null && v > Number(range[1]);
            judged++; if (low || high) bad++;
            badge.textContent = low ? 'Low' : (high ? 'High' : 'OK');
            tile.classList.add(low || high ? 'is-off' : 'is-ok');
        });
        var verdict = root.querySelector('[data-verdict]');
        if (verdict) { verdict.textContent = judged ? (bad ? bad + ' of ' + judged + ' outside the grade' : 'All ' + judged + ' in tolerance') : ''; verdict.classList.toggle('bad', bad > 0); }
        var recal = root.querySelector('[data-recalescence]');
        if (recal) {
            var min = root.querySelector('[name="grey[te_min_c]"]').value, max = root.querySelector('[name="grey[te_max_c]"]').value;
            recal.placeholder = min !== '' && max !== '' ? (Number(max) - Number(min)).toFixed(1) : '';
        }
    }

    document.addEventListener('click', function (e) {
        var b = e.target.closest('[data-now]');
        if (b) { document.getElementById(b.dataset.now).value = stamp(); return; }
        var all = e.target.closest('[data-all-now]');
        if (all) { all.closest('.tf-root').querySelectorAll('.cup-card [data-cup]:not([hidden]) input[type=datetime-local]').forEach(function (i) { i.value = stamp(); }); return; }
        var clear = e.target.closest('[data-clear-cups]');
        if (clear) {
            var root = clear.closest('.tf-root');
            root.querySelectorAll('.cup-card input[type=number], .cup-card input[name$="[cup_batch]"], .curve-bar textarea, .curve-bar input[type=file]').forEach(function (i) { i.value = ''; });
            check(root);
        }
    });
    document.addEventListener('input', function (e) { var root = e.target.closest && e.target.closest('.tf-root'); if (root) check(root); });
    document.addEventListener('change', function (e) {
        var t = e.target.closest && e.target.closest('[data-cup-toggle]'); if (t) applyCup(t);
        var root = e.target.closest && e.target.closest('.tf-root'); if (root) check(root);
    });
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-cup-toggle]').forEach(applyCup);
        document.querySelectorAll('.tf-root').forEach(check);
    });
    // The record-test dialog sets the heat picker when it opens; re-check against that heat's grade.
    document.addEventListener('click', function (e) { if (e.target.closest('[data-quick][data-heat]')) setTimeout(function () { document.querySelectorAll('.tf-root').forEach(check); }); });
})();
</script>
<style>
    .tf { --tf-amber: #fff8e6; --tf-amber-line: #f3d27a; --tf-blue: #eef2ff; --tf-blue-line: #b8c3f5; --tf-green: #ecfbf3; --tf-green-line: #9fdcbc; }
    .tf .field { margin: 0; min-width: 0; }
    .tf label { font-size: 12px; }
    .tf input:not([type=checkbox]):not([type=file]), .tf select { min-height: 32px; padding: 4px 9px; font-size: 13px; width: 100%; max-width: 100%; }
    .tf .mono, .tf-strip .strip-meta { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }

    .tf-strip { display: flex; flex-wrap: wrap; align-items: center; gap: 10px 18px; padding: 10px 12px; border: 1px solid var(--border); border-radius: 10px; background: var(--hover, #f7f8fa); margin-bottom: 12px; }
    .tf-strip .strip-heat { display: inline-flex; align-items: center; gap: 6px; font-size: 16px; }
    .tf-strip .strip-meta { color: var(--muted); font-size: 13px; }
    .tf-strip .strip-meta b { color: var(--text); font-weight: 600; }
    .tf-strip .strip-pick { flex: 1 1 280px; display: flex; align-items: center; gap: 8px; }
    .tf-strip .strip-device { margin-left: auto; display: flex; align-items: center; gap: 8px; }
    .tf-strip .strip-device label, .tf-strip .strip-pick label { margin: 0; white-space: nowrap; color: var(--muted); }
    .tf-strip .strip-device select { min-width: 220px; }

    .tf-sample { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)) minmax(0, 1.6fr); gap: 8px 12px; margin-bottom: 12px; }
    .unit-input, .mini-input { position: relative; display: flex; align-items: center; }
    .unit-input span, .mini-input span { position: absolute; right: 9px; color: var(--muted); font-size: 12px; pointer-events: none; }
    .unit-input input { padding-right: 24px !important; }

    .tf-cups-bar { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 10px; padding: 8px 12px; border: 1px solid var(--border); border-radius: 10px; margin-bottom: 12px; }
    .tf-cups-bar .bar-label { font-size: 12px; font-weight: 700; letter-spacing: .5px; text-transform: uppercase; color: var(--muted); }
    .cup-pill { position: relative; margin: 0; cursor: pointer; }
    .cup-pill input { position: absolute; opacity: 0; width: 1px; height: 1px; }
    .cup-pill span { display: inline-flex; align-items: center; gap: 7px; padding: 6px 12px; border-radius: 7px; border: 1px solid var(--border); font-weight: 600; font-size: 13px; color: var(--muted); }
    .cup-pill input:checked + span { background: var(--text, #1f2937); border-color: var(--text, #1f2937); color: #fff; }
    .cup-pill input:focus-visible + span { outline: 2px solid var(--accent); outline-offset: 1px; }
    .tf-cups-bar .bar-actions { margin-left: auto; display: flex; gap: 10px; align-items: center; }
    .link-btn { appearance: none; border: 0; background: none; padding: 0; min-height: 0; color: var(--muted); cursor: pointer; font: inherit; font-size: 13px; }
    .link-btn:hover { color: var(--text); text-decoration: underline; }
    .dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; }
    .dot-white { background: #f5b301; } .dot-grey { background: #22a565; }

    .cup-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; align-items: start; margin-bottom: 12px; }
    .cup-card { border: 1px solid var(--border); border-radius: 12px; background: var(--surface, #fff); overflow: hidden; }
    .cup-card > header { display: flex; justify-content: space-between; align-items: center; gap: 8px; padding: 9px 14px; border-bottom: 1px solid var(--border); }
    .cup-card > header strong { font-size: 15px; }
    .cup-check { display: inline-flex; align-items: center; gap: 8px; margin: 0; cursor: pointer; font-size: 14px; }
    .cup-check input { width: 18px; height: 18px; min-height: 0; margin: 0; accent-color: var(--accent); flex: none; }
    .cup-card.is-off { background: var(--hover, #f7f8fa); }
    .cup-card.is-off > header { border-bottom: 0; }
    .cup-card.is-off > header .cup-check { color: var(--muted); }
    .cup-tag { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 11px; text-transform: uppercase; letter-spacing: .4px; padding: 3px 8px; border-radius: 6px; background: var(--hover, #f2f4f7); color: var(--muted); white-space: nowrap; }
    .cup-tag.tag-green { background: var(--tf-green); color: #18794e; }
    .cup-body { padding: 10px 14px 12px; }
    .cup-sub { display: flex; justify-content: space-between; align-items: baseline; margin: 2px 0 8px; font-weight: 600; font-size: 13px; color: var(--muted); }
    .cup-sub + .tile-row, .cup-sub + .mini-row { margin-bottom: 12px; }
    .cup-verdict { color: #18794e; font-size: 12px; } .cup-verdict.bad { color: var(--danger); }

    .tile-row { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
    .tf-tile { border: 1px solid var(--border); border-radius: 10px; padding: 8px 10px 7px; background: var(--surface, #fff); }
    .tf-tile.tone-amber { background: var(--tf-amber); border-color: var(--tf-amber-line); }
    .tf-tile.tone-blue { background: var(--tf-blue); border-color: var(--tf-blue-line); }
    .tf-tile.tone-green { background: var(--tf-green); border-color: var(--tf-green-line); }
    .tf-tile.is-off { border-color: var(--danger); box-shadow: 0 0 0 1px var(--danger) inset; }
    .tile-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px; }
    .tile-top label { margin: 0; font-size: 11.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .3px; }
    .tile-badge { font-size: 11px; font-weight: 700; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
    .is-ok .tile-badge { color: #18794e; } .is-off .tile-badge { color: var(--danger); }
    .tf .tf-tile input { min-height: 44px; font-size: 24px !important; font-weight: 600; text-align: center; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; background: var(--surface, #fff); }
    .tile-hint { margin-top: 4px; text-align: center; font-size: 11.5px; color: var(--muted); font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }

    .mini-row { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; }
    .mini-row.mini-6 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .tf-mini { display: flex; justify-content: space-between; align-items: center; gap: 8px; border: 1px solid var(--border); border-radius: 8px; padding: 6px 8px; }
    .tf-mini label { margin: 0; font-weight: 600; font-size: 12.5px; }
    .tf-mini small { display: block; color: var(--muted); font-size: 11px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
    .tf-mini .mini-input { width: 96px; flex: none; }
    .tf .tf-mini input { padding-right: 26px !important; text-align: right; font-weight: 600; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }

    .cup-foot { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 8px; align-items: end; border: 1px solid var(--border); border-radius: 8px; padding: 8px; background: var(--hover, #f7f8fa); }
    .cup-foot label { display: flex; justify-content: space-between; margin: 0 0 3px; font-size: 11.5px; font-weight: 600; text-transform: uppercase; letter-spacing: .3px; color: var(--muted); }
    .now-link { appearance: none; border: 0; background: transparent; padding: 0; min-height: 0; font: inherit; font-size: 11.5px; font-weight: 600; text-transform: none; color: var(--accent); cursor: pointer; }
    .now-link:hover { text-decoration: underline; }

    .curve-bar { border: 1px solid var(--border); border-radius: 10px; padding: 0 14px; margin-bottom: 4px; }
    .curve-bar summary { cursor: pointer; padding: 10px 0; display: flex; gap: 10px; align-items: baseline; }
    .curve-bar summary > span:first-child { font-weight: 700; font-size: 13px; text-transform: uppercase; letter-spacing: .4px; }
    .curve-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
    .curve-grid .field { margin-top: 6px; }

    @media (max-width: 1100px) { .tf-sample { grid-template-columns: repeat(3, minmax(0, 1fr)); } .cup-grid { grid-template-columns: 1fr; } }
    @media (max-width: 640px) {
        .tf-sample, .tile-row, .mini-row, .mini-row.mini-6, .cup-foot, .curve-grid { grid-template-columns: 1fr 1fr; }
        .tf-strip .strip-device { margin-left: 0; } .tf-strip .strip-device select { min-width: 0; }
    }
</style>
@endPushOnce
