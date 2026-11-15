{{-- The list layout of 13a and 24a (tabs, filters, table, side panel), kept with the module so it can move with it. --}}
<style>
.fa-card { border: 1px solid #e6e7e9; border-radius: 8px; background: #fff; font-size: 13px; }
.fa-bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; padding: 14px 28px; border-bottom: 1px solid #e6e7e9; }
.fa-tabs { display: flex; gap: 18px; align-self: stretch; margin: -14px 0; flex-wrap: wrap; }
.fa-tabs a { display: inline-flex; align-items: center; gap: 7px; padding: 14px 2px; white-space: nowrap; color: #55585d; font-weight: 500; text-decoration: none !important; }
.fa-tabs a.on { color: #1c1d1f; font-weight: 600; box-shadow: inset 0 -2px 0 var(--accent); }
.fa-tabs .n { font-size: 12px; font-weight: 500; color: #6b6e73; font-variant-numeric: tabular-nums; } .fa-tabs a.on .n { color: var(--accent); }
.fa-btn { display: inline-block; padding: 7px 14px; border-radius: 6px; background: #fff; border: 1px solid #d9dbde; font-size: 13px; color: #1c1d1f; cursor: pointer; text-decoration: none !important; }
.fa-btn.primary { background: var(--accent); border-color: var(--accent); color: #fff; font-weight: 500; }
.fa-btn:disabled { opacity: .5; cursor: default; }
.fa-grid { display: grid; grid-template-columns: minmax(0, 1fr) 340px; }
.fa-main { padding: 20px 28px; display: flex; flex-direction: column; gap: 16px; min-width: 0; }
.fa-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
.fa-kpi { border: 1px solid #e6e7e9; border-radius: 8px; padding: 12px 14px; }
.fa-kpi .lab, .fa-lab { font-size: 12px; color: #6b6e73; } .fa-kpi .v { font-size: 18px; font-weight: 600; margin: 2px 0; font-variant-numeric: tabular-nums; } .fa-kpi .s { font-size: 12px; color: #6b6e73; }
.fa-filters { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.fa-search { flex: 1; min-width: 180px; max-width: 320px; display: flex; align-items: center; gap: 8px; height: 34px; padding: 0 10px; box-sizing: border-box; border: 1px solid #c9cbcf; border-radius: 6px; background: #fff; }
.fa-search input { flex: 1; min-width: 0; border: 0 !important; padding: 0 !important; height: 32px; font-size: 13px; background: transparent; box-shadow: none !important; outline: 0; }
.fa-chip { position: relative; display: inline-flex; align-items: center; gap: 6px; height: 34px; padding: 0 10px 0 12px; box-sizing: border-box; border: 1px solid #c9cbcf; border-radius: 6px; background: #fff; cursor: pointer; white-space: nowrap; font-size: 13px; color: #1c1d1f; }
.fa-chip .k { color: #55585d; } .fa-chip b { font-weight: 500; }
.fa-chip select { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; }
.fa-table { margin: 0 -12px; overflow-x: auto; }
.fa-table table { width: 100%; border-collapse: collapse; }
.fa-table td, .fa-table th { text-align: left; padding: 9px 12px; border-bottom: 1px solid #eff0f2; vertical-align: top; }
.fa-table th { font-size: 12px; font-weight: 500; color: #55585d; white-space: nowrap; }
.fa-table .r { text-align: right; } .fa-table .nw { white-space: nowrap; font-variant-numeric: tabular-nums; }
.fa-table tbody tr.pick { cursor: pointer; } .fa-table tbody tr.sel { background: #f5f8fe; }
.fa-table tr.sum td { font-weight: 600; border-bottom: 0; }
.fa-sub { font-size: 12px; color: #6b6e73; }
.fa-pill { font-size: 11.5px; padding: 2px 8px; border-radius: 999px; font-weight: 500; white-space: nowrap; display: inline-block; }
.fa-ok { background: #e7f4ec; color: #1d7048; } .fa-warn { background: #fbf0dc; color: #94610a; } .fa-bad { background: #fbe7e5; color: #b3352c; } .fa-info { background: #eaf0fd; color: #2f5fd0; } .fa-grey { background: #f0f1f3; color: #55585d; }
.fa-side { border-left: 1px solid #e6e7e9; border-radius: 0 0 8px 0; padding: 20px 24px; background: #f8f8f9; display: flex; flex-direction: column; gap: 16px; }
.fa-two { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.fa-big { font-size: 16px; font-weight: 600; font-variant-numeric: tabular-nums; }
.fa-sec { font-size: 12px; font-weight: 600; color: #6b6e73; text-transform: uppercase; letter-spacing: .06em; margin: 0 0 8px; }
.fa-row { display: flex; justify-content: space-between; gap: 12px; padding: 7px 0; border-bottom: 1px solid #e6e7e9; }
.fa-row > :last-child { color: #6b6e73; text-align: right; }
.fa-check { display: flex; gap: 10px; align-items: flex-start; padding: 6px 0; } .fa-check .i { width: 18px; flex: none; font-weight: 700; }
.fa-note { border-left: 4px solid #c58a1a; background: #fdf6ea; border-radius: 6px; padding: 10px 14px; font-size: 12.5px; }
.fa-dl { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 14px 24px; }
.fa-dl div > :last-child { margin-top: 2px; }
.fa-dialog { border: 0; border-radius: 10px; padding: 0; width: min(560px, calc(100vw - 32px)); box-shadow: 0 20px 48px rgba(0,0,0,.2); }
.fa-dialog::backdrop { background: rgba(20,20,20,.35); }
.fa-dialog form, .fa-dialog .body { padding: 20px 24px; display: flex; flex-direction: column; gap: 14px; }
.fa-dialog .foot { display: flex; justify-content: flex-end; gap: 8px; }
.fa-dispose { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 24px; }
.fa-dispose-form { display: flex; flex-direction: column; gap: 12px; }
.fa-dispose-form label { display: flex; flex-direction: column; gap: 4px; font-size: 13px; }
.fa-dispose-side { display: flex; flex-direction: column; gap: 18px; background: #fafafa; border-radius: 8px; padding: 14px 16px; }
.fa-choice { border: 0; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 6px; }
.fa-choice label { flex-direction: row !important; align-items: flex-start; gap: 10px !important; padding: 9px 12px; border: 1px solid #d9dbde; border-radius: 8px; cursor: pointer; }
.fa-choice label.on { border-color: var(--accent); background: #f5f8fe; }
.fa-choice label > span { display: flex; flex-direction: column; gap: 2px; }
@media (max-width: 760px) { .fa-dispose { grid-template-columns: minmax(0, 1fr); } }
.fr-card { overflow: visible; }
.fr-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; padding: 8px 20px; min-height: 48px; box-sizing: border-box; }
.fr-title { margin: 0; font-size: 16px; font-weight: 600; }
.fr-actions { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.fr-actions .fa-btn, .fr-pbtns .fa-btn { height: 32px; padding: 0 12px; display: inline-flex; align-items: center; gap: 4px; box-sizing: border-box; white-space: nowrap; }
.fr-dot { width: 7px; height: 7px; border-radius: 50%; background: #c58a1a; display: inline-block; margin-right: 3px; }
.fr-tabs { display: flex; gap: 22px; padding: 0 20px; height: 38px; border-bottom: 1px solid #e6e7e9; overflow-x: auto; white-space: nowrap; }
.fr-tabs a { display: inline-flex; align-items: center; gap: 5px; color: #55585d; text-decoration: none !important; }
.fr-tabs a .n { font-size: 12px; color: #6b6e73; font-variant-numeric: tabular-nums; }
.fr-tabs a.on { font-weight: 600; color: #1c1d1f; box-shadow: inset 0 -2px 0 var(--accent); } .fr-tabs a.on .n { color: var(--accent); }
.fr-tools { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; padding: 8px 20px; border-bottom: 1px solid #e6e7e9; }
.fr-search { max-width: 100%; flex: none; height: 32px; border-color: #c9cbcf; }
.fr-tool { display: inline-flex; align-items: center; gap: 6px; height: 32px; padding: 0 10px; box-sizing: border-box; border: 1px solid #c9cbcf; border-radius: 6px; background: #fff; color: #1c1d1f; font-size: 13px; font-weight: 500; cursor: pointer; white-space: nowrap; list-style: none; }
.fr-tool::-webkit-details-marker, .fr-menu summary::-webkit-details-marker { display: none; }
.fr-tool.on { border-color: var(--accent); color: var(--accent); } .fr-tool b { color: var(--accent); }
.fr-details.on { border-width: 1.5px; background: #eef3fd; }
.fr-sep { width: 1px; height: 20px; background: #e6e7e9; margin: 0 4px; }
.fr-flag { display: inline-flex; align-items: center; gap: 6px; height: 30px; padding: 0 10px; box-sizing: border-box; border: 1px solid #f0dcb4; background: #fdf6ea; border-radius: 15px; font-size: 12.5px; color: #1c1d1f; cursor: pointer; white-space: nowrap; }
.fr-flag b { color: #94610a; } .fr-flag.on { border-color: #c58a1a; box-shadow: inset 0 0 0 1px #c58a1a; }
.fr-right { margin-left: auto; display: flex; gap: 8px; align-items: center; }
.fr-menu { position: relative; } .fr-menu summary { list-style: none; cursor: pointer; }
.fr-pop { position: absolute; z-index: 20; top: calc(100% + 4px); left: 0; min-width: 200px; background: #fff; border: 1px solid #e6e7e9; border-radius: 8px; box-shadow: 0 10px 28px rgba(0,0,0,.12); padding: 6px; display: flex; flex-direction: column; }
.fr-menu.right .fr-pop, .fr-actions .fr-pop { left: auto; right: 0; }
.fr-pop a, .fr-pop button { display: block; text-align: left; padding: 7px 10px; border-radius: 6px; color: #1c1d1f; text-decoration: none !important; background: none; border: 0; font-size: 13px; cursor: pointer; white-space: nowrap; }
.fr-pop a:hover, .fr-pop button:hover { background: #f3f4f6; }
.fr-filters { padding: 12px; gap: 10px; min-width: 240px; } .fr-filters label { display: flex; flex-direction: column; gap: 4px; font-size: 12px; color: #55585d; }
.fr-body { display: grid; grid-template-columns: minmax(0, 1fr); } .fr-body.with-panel { grid-template-columns: minmax(0, 1fr) 340px; }
.fr-list { min-width: 0; } .fr-scroll { overflow-x: auto; } .fr-empty { padding: 20px; margin: 0; }
.fr-table { width: 100%; border-collapse: collapse; }
.fr-table th { height: 34px; padding: 0 12px; background: #f8f8f9; border-bottom: 1px solid #e6e7e9; text-align: left; font-size: 12px; font-weight: 400; color: #55585d; white-space: nowrap; }
.fr-table th.r, .fr-table td.r { text-align: right; } .fr-table th.r .fr-sort { margin-left: auto; }
.fr-table th:first-child, .fr-table td:first-child { padding-left: 20px; } .fr-table th:last-child, .fr-table td:last-child { padding-right: 20px; }
.fr-sort { display: inline-flex; background: none; border: 0; padding: 0; font: inherit; color: inherit; cursor: pointer; } .fr-sort.on { color: #1c1d1f; font-weight: 600; } .fr-arrow { color: var(--accent); white-space: pre; }
.fr-table td { height: 40px; padding: 0 12px; border-bottom: 1px solid #eff0f2; white-space: nowrap; }
.fr-table tbody tr { cursor: pointer; outline: none; } .fr-table tbody tr:hover { background: #fafafb; } .fr-table tbody tr.sel, .fr-table tbody tr:focus-visible { background: #f5f8fe; }
.fr-table .nw { font-variant-numeric: tabular-nums; }
.fr-name { max-width: 380px; overflow: hidden; text-overflow: ellipsis; } .fr-name a { font-weight: 500; color: #1c1d1f; text-decoration: none !important; }
.fr-code { color: #55585d; margin-left: 8px; font-size: 12px; } .fr-warn { margin-left: 6px; font-size: 11.5px; font-weight: 600; color: #94610a; }
.fr-dim { color: #3d4045; } .fr-where { max-width: 180px; overflow: hidden; text-overflow: ellipsis; }
.fr-life { display: flex; align-items: center; gap: 8px; min-width: 110px; }
.fr-track { flex: 1; display: block; height: 6px; border-radius: 3px; background: #e6e7e9; overflow: hidden; } .fr-track > span { display: block; height: 6px; border-radius: 3px; }
.fr-track.big { margin: 8px 0 4px; }
.fr-pct { width: 34px; text-align: right; font-variant-numeric: tabular-nums; } .fr-pct.warn { color: #94610a; font-weight: 600; }
.fr-act { width: 44px; } .fr-more { width: 28px; height: 28px; display: inline-flex !important; align-items: center; justify-content: center; border-radius: 6px; font-size: 16px; color: #55585d; padding: 0 !important; }
.fr-more:hover { background: #f0f1f3; }
.fr-panel { border-left: 1px solid #e6e7e9; background: #f8f8f9; padding: 14px 18px; display: flex; flex-direction: column; gap: 12px; min-width: 0; }
.fr-ph { display: flex; align-items: flex-start; gap: 10px; } .fr-pt { font-size: 15px; font-weight: 600; }
.fr-hide { width: 28px; height: 28px; flex: none; display: inline-flex; align-items: center; justify-content: center; border: 1px solid #d9dbde; border-radius: 6px; background: #fff; color: #55585d; cursor: pointer; }
.fr-pbtns { display: flex; gap: 8px; } .fr-pbtns > .fa-btn.primary { flex: 1; justify-content: center; height: 34px; } .fr-pbtns .fa-btn { height: 34px; } .fr-pbtns .fr-more { width: 34px; height: 34px; border: 1px solid #d9dbde; background: #fff; }
.fr-alert { background: #fdf6ea; border: 1px solid #f0dcb4; border-radius: 6px; padding: 8px 10px; display: flex; align-items: center; gap: 8px; font-size: 12.5px; } .fr-alert > span { flex: 1; } .fr-alert b { color: #94610a; }
.fr-alert .fa-btn { height: 30px; padding: 0 12px; display: inline-flex; align-items: center; }
.fr-kv { display: flex; justify-content: space-between; gap: 12px; padding: 6px 0; border-bottom: 1px solid #eff0f2; } .fr-kv > :first-child { color: #55585d; } .fr-kv > :last-child { font-weight: 500; text-align: right; }
.fr-recent { display: flex; justify-content: space-between; gap: 10px; padding: 5px 0; border-bottom: 1px solid #eff0f2; font-size: 12.5px; } .fr-recent .fa-sub { white-space: nowrap; }
.fr-foot { display: flex; align-items: center; gap: 18px; flex-wrap: wrap; padding: 8px 20px; min-height: 44px; box-sizing: border-box; border-top: 1px solid #e6e7e9; background: #f8f8f9; border-radius: 0 0 8px 8px; }
.fr-foot b { font-weight: 600; }
.fr-rows { margin-left: auto; display: flex; align-items: center; gap: 6px; color: #55585d; }
.fr-rows button { min-width: 30px; height: 30px; border-radius: 6px; border: 1px solid transparent; background: none; color: #55585d; cursor: pointer; font-size: 13px; }
.fr-rows button.on { border-color: var(--accent); background: #eaf0fd; color: var(--accent); font-weight: 600; }
.fr-pages nav { display: inline-block; } .fr-pages { margin-right: 10px; }
@media (max-width: 1000px) { .fr-body.with-panel { grid-template-columns: minmax(0, 1fr); } .fr-panel { border-left: 0; border-top: 1px solid #e6e7e9; } }
@media (max-width: 640px) { .fr-head, .fr-tabs, .fr-tools, .fr-foot { padding-left: 16px; padding-right: 16px; } .fr-search { width: 100% !important; } .fr-right { margin-left: 0; } .fr-table th:first-child, .fr-table td:first-child { padding-left: 16px; } }
@media (max-width: 1000px) { .fa-grid { grid-template-columns: minmax(0, 1fr); } .fa-side { border-left: 0; border-top: 1px solid #e6e7e9; border-radius: 0 0 8px 8px; } .fa-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); } .fa-bar, .fa-main { padding-left: 16px; padding-right: 16px; } }
</style>
