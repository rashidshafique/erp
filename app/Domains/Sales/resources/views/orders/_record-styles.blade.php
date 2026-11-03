{{-- Styles shared by the sales order list and record pages (designs 1e, 6a). --}}
<style>
    .el-card { background: var(--surface, #fff); border: 1px solid var(--border); border-radius: 10px; }
    .el-bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; padding: 14px 28px; border-bottom: 1px solid var(--border); }
    .el-tabs { display: flex; gap: 4px; flex-wrap: wrap; font-size: 13px; } .el-tabs a { padding: 4px 10px; border-radius: 6px; color: var(--muted); text-decoration: none; } .el-tabs a.on { background: #f0f1f3; color: var(--text); }
    .el-btn { padding: 7px 14px; border-radius: 6px; background: var(--surface, #fff); border: 1px solid var(--border); font-size: 13px; color: var(--text); text-decoration: none !important; cursor: pointer; white-space: nowrap; }
    .el-btn.primary { background: var(--accent); border-color: var(--accent); color: #fff; font-weight: 500; }
    .el-grid { display: grid; grid-template-columns: minmax(0, 1fr) 300px; }
    .el-main { padding: 20px 28px; display: flex; flex-direction: column; gap: 16px; min-width: 0; }
    .el-side { border-left: 1px solid var(--border); padding: 20px 24px; background: #f8f8f9; display: flex; flex-direction: column; gap: 18px; font-size: 13px; }
    .el-filters { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; font-size: 13px; } .el-filters input[type=search] { flex: 1; max-width: 320px; } .el-filters select { width: auto; }
    .el-table { margin: 0 -12px; overflow-x: auto; } .el-table table { width: 100%; } .el-table td { vertical-align: top; }
    .el-table tbody tr { cursor: pointer; } .el-table tbody tr:hover { background: #f5f8fe; }
    .el-sub { font-size: 12px; color: var(--muted); }
    .el-pill { font-size: 11.5px; padding: 2px 8px; border-radius: 999px; font-weight: 500; white-space: nowrap; background: #f0f1f3; color: #6b6e73; }
    .el-pill.el-blue { background: #eaf0fd; color: var(--accent); } .el-pill.el-ok { background: #e7f4ec; color: #1d7048; } .el-pill.el-warn { background: #fbf0dc; color: #94610a; } .el-pill.el-bad { background: #fbe7e5; color: #b3352c; }
    .el-h { font-size: 12px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .06em; }
    .el-row { display: flex; justify-content: space-between; gap: 12px; padding: 7px 0; border-bottom: 1px solid var(--border); } .el-row:last-child { border-bottom: 0; } .el-row span:last-child { color: var(--muted); text-align: right; }
    .el-note { font-size: 12px; color: var(--muted); }
    .el-r { text-align: right; }
    @media (max-width: 1100px) { .el-grid { grid-template-columns: 1fr; } .el-side { border-left: 0; border-top: 1px solid var(--border); } }
    @media (max-width: 700px) { .el-bar, .el-main { padding-left: 16px; padding-right: 16px; } }
    .el-facts { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 18px; padding: 18px 28px; font-size: 13px; border-bottom: 1px solid var(--border); }
    .el-todo { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 16px; align-items: center; padding: 12px 14px; border: 1px solid #f3dfb8; border-radius: 8px; background: var(--surface, #fff); } .el-todo.bad { border-color: #f2cfcb; }
    .el-todo-t { font-weight: 600; font-size: 13px; color: #94610a; } .el-todo.bad .el-todo-t { color: #b3352c; } .el-todo-m { font-size: 12.5px; color: #55585d; margin-top: 2px; }
    @media (max-width: 1100px) { .el-facts { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
</style>
