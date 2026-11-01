{{-- Shared look of the accounts screens (design T19): a card with a header bar, tables, fields, dialogs and pills, values read off the design. --}}
@once
<style>
.ac-card { position: relative; border: 1px solid #e6e7e9; border-radius: 8px; background: #fff; font-size: 13px; color: #1c1d1f; }
.ac-bar { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; padding: 14px 28px; border-bottom: 1px solid #e6e7e9; }
.ac-crumb { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; font-size: 13px; }
.ac-crumb > :first-child { font-weight: 600; }
.ac-crumb .sep { color: #6b6e73; }
.ac-crumb a { color: inherit; }
.ac-actions { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.ac-btn { display: inline-flex; align-items: center; padding: 7px 14px; border-radius: 6px; background: #fff; border: 1px solid #d9dbde; font-size: 13px; line-height: normal; color: #1c1d1f; white-space: nowrap; cursor: pointer; text-decoration: none !important; min-height: 0; box-shadow: none; }
.ac-btn:hover { color: #1c1d1f; background: #f8f8f9; }
.ac-btn.primary { background: #2f5fd0; border-color: #2f5fd0; color: #fff; font-weight: 500; }
.ac-btn.primary:hover { background: #1f3f8f; }
.ac-btn.primary[disabled], .ac-btn.primary.off { background: #e6e7e9; border-color: #e6e7e9; color: #9a9da3; cursor: not-allowed; }
.ac-btn.danger { color: #b3352c; }
.ac-btn[disabled]:not(.primary) { color: #b4b7bc; cursor: not-allowed; background: #fff; }
.ac-body { padding: 16px 28px 24px; display: flex; flex-direction: column; gap: 12px; }
.ac-note { font-size: 12px; color: #6b6e73; text-wrap: pretty; }
.ac-muted { color: #6b6e73; }
.ac-filters { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; font-size: 13px; }
.ac-input, .ac-card select, .ac-card textarea, .ac-dialog select, .ac-dialog input, .ac-dialog textarea { width: 100%; box-sizing: border-box; padding: 8px 10px; border: 1px solid #d9dbde; border-radius: 6px; font-size: 13px; background: #fff; font-family: inherit; color: #1c1d1f; min-height: 0; }
.ac-chip { padding: 6px 10px; border: 1px solid #d9dbde; border-radius: 6px; background: #fff; white-space: nowrap; font-size: 13px; width: auto; }
.ac-tick { display: inline-flex; align-items: center; justify-content: center; flex: none; width: 15px; height: 15px; border-radius: 3px; box-sizing: border-box; border: 1px solid #c4c7cc; background: #fff; color: #fff; font-size: 10px; cursor: pointer; }
.ac-tick.on { border-color: #2f5fd0; background: #2f5fd0; }
.ac-check { display: flex; gap: 7px; align-items: center; cursor: pointer; color: #6b6e73; white-space: nowrap; user-select: none; }
.ac-link { cursor: pointer; font-size: 12.5px; white-space: nowrap; color: #2f5fd0; background: none; border: 0; padding: 0; min-height: 0; box-shadow: none; }
.ac-link:hover { color: #1f3f8f; background: none; }
.ac-toast { display: flex; justify-content: space-between; gap: 12px; padding: 9px 12px; border-radius: 7px; background: #e7f4ec; color: #1d7048; font-size: 12.5px; }
.ac-toast.bad { background: #fbe7e5; color: #b3352c; }
.ac-toast.warn { background: #fbf0dc; color: #94610a; }
.ac-tag { font-size: 11px; padding: 1px 7px; border-radius: 4px; border: 1px solid #c4c7cc; color: #6b6e73; white-space: nowrap; }
.ac-tag.dc { border-color: #e5c78a; color: #94610a; }
.ac-tag.off { border-color: #d9dbde; }
.ac-tag.new { border-color: #9db4ec; color: #2f5fd0; }
.ac-pill { display: inline-block; font-size: 11.5px; padding: 2px 8px; border-radius: 999px; background: #f0f1f3; color: #6b6e73; font-weight: 500; white-space: nowrap; }
.ac-pill.ok { background: #e7f4ec; color: #1d7048; }
.ac-pill.warn { background: #fbf0dc; color: #94610a; }
.ac-pill.bad { background: #fbe7e5; color: #b3352c; }
.ac-sec { font-size: 12px; font-weight: 600; color: #6b6e73; text-transform: uppercase; letter-spacing: .06em; }
.ac-table { margin: 0 -12px; overflow-x: auto; }
.ac-table table { border-collapse: collapse; width: 100%; }
.ac-table th { text-align: left; font-weight: 500; font-size: 11.5px; color: #6b6e73; padding: 9px 12px; border-bottom: 1px solid #e6e7e9; white-space: nowrap; background: none; text-transform: none; letter-spacing: 0; }
.ac-table td { padding: 10px 12px; border-bottom: 1px solid #eff0f2; font-size: 13px; vertical-align: middle; }
.ac-table .r { text-align: right; }
.ac-table tr.last td, .ac-table tbody tr:last-child td { border-bottom: 0; }
.ac-table code, .ac-card code, .ac-dialog code { font-family: ui-monospace, "SF Mono", Menlo, monospace; font-size: .92em; background: none; padding: 0; color: inherit; }
.ac-seg { display: flex; gap: 4px; padding: 3px; border-radius: 8px; background: #f0f1f3; align-self: flex-start; flex-wrap: wrap; }
.ac-seg > * { padding: 5px 12px; border-radius: 6px; font-size: 12.5px; cursor: pointer; color: #6b6e73; background: transparent; border: 0; min-height: 0; box-shadow: none; }
.ac-seg > .on { background: #fff; color: #1c1d1f; box-shadow: 0 1px 2px rgba(0,0,0,.12); }
.ac-seg > [disabled] { color: #b4b7bc; cursor: not-allowed; }
.ac-field { display: flex; flex-direction: column; gap: 5px; }
.ac-field > .lab { font-size: 12px; color: #6b6e73; }
.ac-field > .lab .req { color: #b3352c; }
.ac-field > .hint { font-size: 12px; color: #6b6e73; text-wrap: pretty; }
.ac-locked { padding: 8px 10px; border-radius: 6px; background: #f3f4f5; color: #6b6e73; font-size: 13px; display: flex; justify-content: space-between; gap: 8px; }
.ac-locked small { font-size: 11px; }
.ac-err { font-size: 12px; color: #b3352c; }
/* dialog (19i to 19l) */
.ac-overlay { position: fixed; inset: 0; z-index: 60; background: rgba(28,29,31,.28); display: flex; justify-content: center; align-items: flex-start; padding: 56px 16px 16px; overflow: auto; }
.ac-dialog { width: 880px; max-width: 100%; background: #fff; border-radius: 10px; box-shadow: 0 12px 40px rgba(0,0,0,.18); display: flex; flex-direction: column; font-size: 13px; color: #1c1d1f; }
.ac-dialog-head { display: flex; justify-content: space-between; align-items: center; padding: 16px 24px; border-bottom: 1px solid #e6e7e9; }
.ac-dialog-head h2 { margin: 0; font-size: 15px; font-weight: 600; }
.ac-x { cursor: pointer; color: #6b6e73; font-size: 18px; line-height: 1; background: none; border: 0; padding: 0; min-height: 0; box-shadow: none; }
.ac-dialog-grid { display: grid; grid-template-columns: minmax(0,1fr) 340px; }
.ac-dialog-main { padding: 20px 24px; display: flex; flex-direction: column; gap: 14px; }
.ac-dialog-side { border-left: 1px solid #e6e7e9; background: #f8f8f9; padding: 20px; display: flex; flex-direction: column; gap: 6px; max-height: 520px; overflow: auto; }
.ac-dialog-side .row { display: grid; grid-template-columns: minmax(0,1fr) 86px; gap: 10px; padding: 6px 8px; border-radius: 5px; font-size: 12.5px; }
.ac-dialog-side .row.head { padding: 0 8px 6px; font-size: 11.5px; color: #6b6e73; border-bottom: 1px solid #e6e7e9; border-radius: 0; }
.ac-dialog-side .row.on { background: #eaf0fd; }
.ac-dialog-side .row code { color: #6b6e73; text-align: right; }
.ac-dialog-side .row .tag { font-size: 11px; color: #2f5fd0; }
.ac-dialog-foot { display: flex; align-items: center; gap: 10px; padding: 14px 24px; border-top: 1px solid #e6e7e9; }
.ac-dialog-foot .msg { flex: 1; font-size: 12.5px; color: #6b6e73; text-wrap: pretty; }
.ac-dialog-foot .msg.bad { color: #b3352c; }
.ac-box { display: flex; gap: 10px; align-items: flex-start; cursor: pointer; }
.ac-box .ac-tick { width: 16px; height: 16px; margin-top: 1px; font-size: 11px; }
@media (max-width: 900px) { .ac-dialog-grid { grid-template-columns: minmax(0,1fr); } .ac-dialog-side { border-left: 0; border-top: 1px solid #e6e7e9; } .ac-bar, .ac-body { padding-left: 16px; padding-right: 16px; } }
@media print { .ac-actions, .ac-filters .ac-btn { display: none !important; } .ac-card { border: 0; } }
</style>
@endonce
