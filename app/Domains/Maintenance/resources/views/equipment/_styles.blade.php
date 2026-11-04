{{-- Styles for the maintenance screen (design 7k): the bar and tabs, four figures, a table with a 340px rail, and the
     pills and meter bars the design uses. --}}
<style>
    .el-card { background: var(--surface, #fff); border: 1px solid var(--border); border-radius: 10px; }
    .el-bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; padding: 14px 28px; border-bottom: 1px solid var(--border); }
    .el-tabs { display: flex; gap: 4px; flex-wrap: wrap; font-size: 13px; } .el-tabs a { padding: 4px 10px; border-radius: 6px; color: var(--muted); text-decoration: none; } .el-tabs a.on { background: #f0f1f3; color: var(--text); }
    .el-btn { padding: 7px 14px; border-radius: 6px; background: var(--surface, #fff); border: 1px solid var(--border); font-size: 13px; color: var(--text); text-decoration: none !important; cursor: pointer; white-space: nowrap; }
    .el-btn.primary { background: var(--accent); border-color: var(--accent); color: #fff; font-weight: 500; }
    .el-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); border-bottom: 1px solid var(--border); }
    .el-stat { padding: 16px 28px; min-width: 0; } .el-stat + .el-stat { border-left: 1px solid var(--border); }
    .el-stat-label { font-size: 12px; color: var(--muted); } .el-stat-value { font-size: 24px; font-weight: 600; line-height: 1.2; } .el-stat-note { font-size: 12px; color: var(--muted); }
    .el-stat-note.bad { color: #b3352c; }
    .el-grid { display: grid; grid-template-columns: minmax(0, 1fr) 340px; }
    .el-main { padding: 20px 28px; display: flex; flex-direction: column; gap: 16px; min-width: 0; }
    .el-side { border-left: 1px solid var(--border); padding: 20px 24px; background: #f8f8f9; display: flex; flex-direction: column; gap: 14px; font-size: 13px; }
    .el-table { margin: 0 -12px; overflow-x: auto; } .el-table table { width: 100%; } .el-table td { vertical-align: middle; }
    .el-sub { font-size: 12px; color: var(--muted); }
    .el-pill { font-size: 12px; padding: 2px 8px; border-radius: 999px; font-weight: 500; white-space: nowrap; background: #f0f1f3; color: #55585d; }
    .el-pill.blue { background: #eaf0fd; color: var(--accent); } .el-pill.warn { background: #fbf0dc; color: #94610a; } .el-pill.bad { background: #fbe7e5; color: #b3352c; }
    .el-pill-btn { border: 0; cursor: pointer; font: inherit; }
    .el-code { border: 0; background: none; padding: 0; font: inherit; color: inherit; cursor: pointer; } .el-code:hover { text-decoration: underline; }
    .el-h { font-size: 12px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .06em; }
    .el-row { display: flex; justify-content: space-between; gap: 12px; padding: 7px 0; border-bottom: 1px solid var(--border); } .el-row:last-child { border-bottom: 0; } .el-row span:last-child { color: var(--muted); text-align: right; }
    .el-note { font-size: 12px; color: var(--muted); }
    .el-r { text-align: right; }
    .el-meter { display: flex; flex-direction: column; gap: 4px; } .el-meter-line { display: flex; justify-content: space-between; gap: 12px; } .el-meter-line span:last-child { color: var(--muted); }
    .el-track { width: 100%; height: 6px; border-radius: 3px; background: #eff0f2; } .el-fill { height: 6px; border-radius: 3px; background: var(--accent); } .el-fill.warn { background: #94610a; }
    .el-tools { display: flex; gap: 8px; align-items: center; justify-content: space-between; flex-wrap: wrap; }
    .qd.mt-modal { width: min(680px, calc(100vw - 32px)); } .qd.mt-modal.mt-wide { width: min(760px, calc(100vw - 32px)); } .qd.mt-modal.mt-narrow { width: min(560px, calc(100vw - 32px)); }
    .mt-modal .qd-body { padding: 20px 24px 4px; } .mt-modal .qd-foot { padding: 14px 24px 16px; }
    .mt-modal .qd-fields { gap: 14px; }
    @media (max-width: 1100px) { .el-grid { grid-template-columns: 1fr; } .el-side { border-left: 0; border-top: 1px solid var(--border); } .el-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } .el-stat:nth-child(3) { border-left: 0; border-top: 1px solid var(--border); } .el-stat:nth-child(4) { border-top: 1px solid var(--border); } }
    @media (max-width: 700px) { .el-bar, .el-main { padding-left: 16px; padding-right: 16px; } .el-stat { padding: 14px 16px; } .el-stats { grid-template-columns: 1fr; } .el-stat + .el-stat { border-left: 0; border-top: 1px solid var(--border); } }
</style>
