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
@media (max-width: 1000px) { .fa-grid { grid-template-columns: minmax(0, 1fr); } .fa-side { border-left: 0; border-top: 1px solid #e6e7e9; border-radius: 0 0 8px 8px; } .fa-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); } .fa-bar, .fa-main { padding-left: 16px; padding-right: 16px; } }
</style>
