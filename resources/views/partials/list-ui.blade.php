{{-- The list layout of the fixed asset register (design 28c), shared by the other lists: a head row with the title and the
     page's buttons, the tabs, a tools row (search, Filters menu, quick chips, and on the right Export and the Details toggle),
     then the table with the side panel beside it while Details is on. --}}
@once
<style>
.ls-card { border: 1px solid #e6e7e9; border-radius: 8px; background: #fff; font-size: 13px; }
.ls-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; padding: 8px 20px; min-height: 48px; box-sizing: border-box; }
.ls-head + .ls-tools { border-top: 1px solid #e6e7e9; }
.ls-title { margin: 0; font-size: 16px; font-weight: 600; }
.ls-actions { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.ls-btn { height: 32px; padding: 0 12px; display: inline-flex; align-items: center; gap: 4px; box-sizing: border-box; white-space: nowrap; border-radius: 6px; background: #fff; border: 1px solid #d9dbde; font-size: 13px; color: #1c1d1f; cursor: pointer; text-decoration: none !important; }
.ls-btn.primary { background: var(--accent); border-color: var(--accent); color: #fff; font-weight: 500; }
.ls-tabs { display: flex; gap: 22px; padding: 0 20px; height: 38px; border-top: 1px solid #e6e7e9; border-bottom: 1px solid #e6e7e9; overflow-x: auto; white-space: nowrap; }
.ls-tabs a, .ls-tabs button { display: inline-flex; align-items: center; gap: 5px; color: #55585d; text-decoration: none !important; background: none; border: 0; padding: 0; font: inherit; cursor: pointer; }
.ls-tabs .n { font-size: 12px; color: #6b6e73; font-variant-numeric: tabular-nums; } .ls-tabs .n.bad { color: #b3352c; }
.ls-tabs .on { font-weight: 600; color: #1c1d1f; box-shadow: inset 0 -2px 0 var(--accent); } .ls-tabs .on .n { color: var(--accent); }
.ls-tabs .dot { width: 7px; height: 7px; border-radius: 50%; }
.ls-tools { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; padding: 8px 20px; border-bottom: 1px solid #e6e7e9; }
.ls-search { display: flex; align-items: center; gap: 8px; width: 280px; max-width: 100%; height: 32px; padding: 0 10px; box-sizing: border-box; border: 1px solid #c9cbcf; border-radius: 6px; background: #fff; }
.ls-search input { flex: 1; min-width: 0; min-height: 0; border: 0 !important; padding: 0 !important; margin: 0; height: 30px; font-size: 13px; background: transparent; box-shadow: none !important; outline: 0; }
.ls-tool { display: inline-flex; align-items: center; gap: 6px; height: 32px; padding: 0 10px; box-sizing: border-box; border: 1px solid #c9cbcf; border-radius: 6px; background: #fff; color: #1c1d1f; font-size: 13px; font-weight: 500; cursor: pointer; white-space: nowrap; list-style: none; text-decoration: none !important; }
.ls-tool::-webkit-details-marker, .ls-menu summary::-webkit-details-marker { display: none; }
.ls-tool.on { border-color: var(--accent); color: var(--accent); } .ls-tool b { color: var(--accent); }
.ls-details.on { border-width: 1.5px; background: #eef3fd; }
.ls-sep { width: 1px; height: 20px; background: #e6e7e9; margin: 0 4px; }
.ls-flag { display: inline-flex; align-items: center; gap: 6px; height: 30px; padding: 0 10px; box-sizing: border-box; border: 1px solid #f0dcb4; background: #fdf6ea; border-radius: 15px; font-size: 12.5px; color: #1c1d1f; cursor: pointer; white-space: nowrap; }
.ls-flag b { color: #94610a; } .ls-flag.on { border-color: #c58a1a; box-shadow: inset 0 0 0 1px #c58a1a; }
.ls-right { margin-left: auto; display: flex; gap: 8px; align-items: center; }
.ls-menu { position: relative; } .ls-menu summary { list-style: none; cursor: pointer; }
.ls-pop { position: absolute; z-index: 20; top: calc(100% + 4px); left: 0; min-width: 200px; background: #fff; border: 1px solid #e6e7e9; border-radius: 8px; box-shadow: 0 10px 28px rgba(0,0,0,.12); padding: 6px; display: flex; flex-direction: column; }
.ls-menu.right .ls-pop, .ls-actions .ls-pop { left: auto; right: 0; }
.ls-pop a, .ls-pop button { display: block; text-align: left; padding: 7px 10px; border-radius: 6px; color: #1c1d1f; text-decoration: none !important; background: none; border: 0; font-size: 13px; cursor: pointer; white-space: nowrap; }
.ls-pop a:hover, .ls-pop button:hover { background: #f3f4f6; }
.ls-filters { padding: 12px; gap: 10px; min-width: 240px; } .ls-filters label { display: flex; flex-direction: column; gap: 4px; font-size: 12px; color: #55585d; }
.ls-select { width: auto; max-width: 240px; height: 32px; min-height: 0; margin: 0; padding: 0 8px; font-size: 13px; border-radius: 6px; }
.ls-filters select { width: 100%; margin: 0; min-height: 32px; padding: 4px 8px; font-size: 13px; }
.ls-body { display: grid; grid-template-columns: minmax(0, 1fr); } .ls-body.with-panel { grid-template-columns: minmax(0, 1fr) 340px; }
.ls-list { min-width: 0; } .ls-scroll { overflow-x: auto; } .ls-empty { padding: 20px; margin: 0; color: #6b6e73; }
.ls-list table { width: 100%; border-collapse: collapse; margin: 0; }
.ls-list th { height: 34px; padding: 0 12px; background: #f8f8f9; border-bottom: 1px solid #e6e7e9; text-align: left; font-size: 12px; font-weight: 400; color: #55585d; white-space: nowrap; }
.ls-list td { padding: 9px 12px; border-bottom: 1px solid #eff0f2; text-align: left; vertical-align: top; }
.ls-list th.r, .ls-list td.r { text-align: right; }
.ls-list th:first-child, .ls-list td:first-child { padding-left: 20px; } .ls-list th:last-child, .ls-list td:last-child { padding-right: 20px; }
.ls-list tbody tr { cursor: pointer; outline: none; } .ls-list tbody tr:hover { background: #fafafb; } .ls-list tbody tr.sel, .ls-list tbody tr:focus-visible { background: #f5f8fe; }
.ls-pager { padding: 8px 20px; border-top: 1px solid #e6e7e9; } .ls-pager:empty { display: none; }
.ls-panel { border-left: 1px solid #e6e7e9; background: #f8f8f9; padding: 14px 18px; display: flex; flex-direction: column; gap: 14px; min-width: 0; border-radius: 0 0 8px 0; }
.ls-ph { display: flex; align-items: flex-start; gap: 10px; } .ls-ph > :first-child { flex: 1; min-width: 0; }
.ls-hide { width: 28px; height: 28px; flex: none; display: inline-flex; align-items: center; justify-content: center; border: 1px solid #d9dbde; border-radius: 6px; background: #fff; color: #55585d; cursor: pointer; }
.ls-more { width: 28px; height: 28px; display: inline-flex !important; align-items: center; justify-content: center; border-radius: 6px; font-size: 16px; color: #55585d; padding: 0 !important; } .ls-more:hover { background: #f0f1f3; }
@media (max-width: 1000px) { .ls-body.with-panel { grid-template-columns: minmax(0, 1fr); } .ls-panel { border-left: 0; border-top: 1px solid #e6e7e9; border-radius: 0 0 8px 8px; } }
@media (max-width: 640px) { .ls-head, .ls-tabs, .ls-tools, .ls-pager { padding-left: 16px; padding-right: 16px; } .ls-search { width: 100%; } .ls-right { margin-left: 0; } .ls-list th:first-child, .ls-list td:first-child { padding-left: 16px; } }
</style>
@endonce
