<style>
.hr-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); background: #fff; border: 1px solid var(--border, #e2e8f0); border-radius: 12px; margin: 0 0 14px; }
.hr-card { display: flex; align-items: center; gap: 12px; padding: 14px 16px; border-left: 1px solid #eef2f6; min-width: 0; }
.hr-card:first-child { border-left: 0; }
.hr-ico { flex: none; display: inline-flex; width: 38px; height: 38px; align-items: center; justify-content: center; border-radius: 10px; background: #fff; box-shadow: 0 1px 3px rgb(15 23 42 / 12%); color: var(--accent-dark); }
.hr-card .hr-l { font-size: 12px; color: #64748b; }
.hr-card .hr-n { display: flex; align-items: baseline; gap: 6px; font-size: 24px; font-weight: 600; line-height: 1.15; color: #0f172a; }
.hr-d { font-size: 11px; font-weight: 500; }
.hr-d.up { color: #16a34a; } .hr-d.down { color: #dc2626; } .hr-d.flat { color: #94a3b8; }
.hr-person { padding: 0; border: 0; background: transparent; color: var(--accent-dark); font: inherit; font-weight: 600; cursor: pointer; text-align: left; }
.hr-person:hover { text-decoration: underline; }
/* side panel */
.hr-veil { position: fixed; inset: 0; z-index: 60; background: rgb(15 23 42 / 40%); opacity: 0; pointer-events: none; transition: opacity .18s; }
.hr-veil.on { opacity: 1; pointer-events: auto; }
.hr-drawer { position: fixed; top: 0; right: 0; bottom: 0; z-index: 61; width: min(620px, 100vw); display: flex; flex-direction: column; background: #fff; box-shadow: -12px 0 40px rgb(15 23 42 / 18%); transform: translateX(105%); transition: transform .2s ease; }
.hr-drawer.on { transform: none; }
.hr-drawer-body { flex: 1; overflow-y: auto; }
.hr-dh { display: flex; align-items: center; gap: 12px; padding: 16px 20px; border-bottom: 1px solid #e2e8f0; }
.hr-dh .hr-avatar { flex: none; width: 46px; height: 46px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; background: color-mix(in srgb, var(--accent) 14%, #fff); color: var(--accent-dark); font-weight: 700; }
.hr-dh h2 { margin: 0; font-size: 17px; }
.hr-dh .sub { font-size: 12.5px; color: #64748b; }
.hr-chip { display: inline-block; margin-left: 6px; padding: 1px 9px; border-radius: 10px; background: #dcfce7; color: #15803d; font-size: 11px; font-weight: 600; vertical-align: middle; }
.hr-nav { margin-left: auto; display: flex; align-items: center; gap: 6px; font-size: 12.5px; color: #64748b; }
.hr-nav button { width: 30px; height: 30px; border: 1px solid #e2e8f0; border-radius: 8px; background: #fff; cursor: pointer; }
.hr-nav button:disabled { opacity: .4; cursor: default; }
.hr-tabs { display: flex; gap: 22px; padding: 0 20px; border-bottom: 1px solid #e2e8f0; }
.hr-tabs button { padding: 12px 0; border: 0; border-bottom: 2px solid transparent; background: transparent; font: inherit; font-size: 13.5px; color: #64748b; cursor: pointer; }
.hr-tabs button.on { color: #0f172a; border-color: var(--accent); font-weight: 600; }
.hr-pane { display: none; padding: 16px 20px 28px; } .hr-pane.on { display: block; }
.hr-tools { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 14px; }
.hr-tools select { width: auto; max-width: 160px; }
.hr-mini { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 18px; }
.hr-mini > div { padding: 12px 14px; border: 1px solid #e2e8f0; border-radius: 10px; }
.hr-mini .l { font-size: 12px; color: #64748b; }
.hr-mini .n { font-size: 20px; font-weight: 600; }
.hr-key { display: flex; gap: 16px; font-size: 12px; color: #475569; margin: 6px 0 12px; flex-wrap: wrap; }
.hr-key i { display: inline-block; width: 9px; height: 9px; border-radius: 2px; margin-right: 6px; }
.tl-row { margin: 0 0 16px; }
.tl-head { display: flex; justify-content: space-between; font-size: 14px; font-weight: 600; margin-bottom: 4px; }
.tl-head span { font-weight: 400; font-size: 12.5px; color: #475569; }
.tl-main { display: grid; grid-template-columns: 62px 1fr 62px; gap: 10px; align-items: end; }
.tl-t { font-size: 12px; line-height: 1.3; } .tl-t small { display: block; color: #94a3b8; font-size: 10.5px; }
.tl-t.r { text-align: right; }
.tl-axis { position: relative; height: 14px; font-size: 10px; color: #94a3b8; }
.tl-axis span { position: absolute; transform: translateX(-50%); }
.tl-track { position: relative; height: 22px; background: #f1f5f9; border-radius: 6px; overflow: hidden; }
.tl-track.off { background: repeating-linear-gradient(45deg, #f1f5f9, #f1f5f9 6px, #e8edf3 6px, #e8edf3 12px); }
.tl-seg { position: absolute; top: 0; bottom: 0; }
.tl-seg.w { background: #7c3aed; } .tl-seg.o { background: #f59e0b; } .tl-seg.l { background: #ef4444; }
.tl-note { font-size: 12px; color: #64748b; padding-top: 3px; }
.hr-tbl { width: 100%; border-collapse: collapse; font-size: 13px; } .hr-tbl th { text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: .5px; color: #64748b; padding: 6px 8px; border-bottom: 1px solid #e2e8f0; } .hr-tbl td { padding: 8px; border-bottom: 1px solid #f1f5f9; }
@media (max-width: 600px) { .hr-mini { grid-template-columns: repeat(2, 1fr); } .tl-main { grid-template-columns: 52px 1fr 52px; } }
</style>
<?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/hr/_ui.blade.php ENDPATH**/ ?>