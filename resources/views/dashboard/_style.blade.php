<style>
.dx{--dx-line:#e2e8f0;--dx-ink:#0f172a;--dx-sub:#475569;--dx-mute:#64748b;--dx-red:#ef4444;--dx-amber:#b45309;display:block;color:var(--dx-ink)}
.dx *{box-sizing:border-box}
.dx .mono{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-variant-numeric:tabular-nums}
.dx .card{background:#fff;border:1px solid var(--dx-line);border-radius:16px;box-shadow:0 1px 2px rgba(15,23,42,.03);padding:18px 20px;margin:0 0 16px}
.dx .card:last-child{margin-bottom:0}
.dx .chead>div:first-child{flex:1 1 150px;min-width:0}.dx .chead>a,.dx .chead>.tabs,.dx .chead>.pill{flex:none}
.dx .card h2{font-size:16px;font-weight:650;margin:0;color:var(--dx-ink);line-height:1.3}
.dx .card .sub{font-size:12.5px;color:var(--dx-mute);margin:3px 0 0}
.dx .chead{display:flex;flex-wrap:wrap;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:14px}
.dx .cap{font-size:11px;font-weight:650;letter-spacing:.05em;text-transform:uppercase;color:var(--dx-mute)}
.dx .pill{display:inline-block;font-size:11px;font-weight:650;letter-spacing:.03em;border-radius:999px;padding:2px 9px;background:#f1f5f9;color:var(--dx-sub);white-space:nowrap}
.dx .pill.red{background:#fef2f2;color:#b91c1c}.dx .pill.amber{background:#fffbeb;color:var(--dx-amber)}.dx .pill.blue{background:color-mix(in srgb,var(--accent) 12%,#fff);color:var(--accent-hover)}.dx .pill.green{background:#ecfdf5;color:#047857}
.dx .dot{display:inline-block;width:8px;height:8px;border-radius:50%;background:var(--accent);margin-right:6px;vertical-align:1px}
.dx .btnx{display:inline-flex;align-items:center;gap:6px;min-height:34px;padding:0 13px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;color:var(--dx-ink);font-weight:550;font-size:13px;text-decoration:none}
.dx .btnx:hover{background:#f8fafc;text-decoration:none}.dx .btnx.primary{background:var(--accent);border-color:var(--accent);color:#fff}.dx .btnx.primary:hover{background:var(--accent-hover)}
/* hero */
.dx .hero{display:flex;flex-wrap:wrap;gap:16px;align-items:flex-end;justify-content:space-between}
.dx .hero h1{font-size:28px;line-height:1.15;font-weight:700;margin:6px 0 6px;letter-spacing:-.01em}
.dx .hero p{margin:0;color:var(--dx-sub);max-width:520px}
.dx .hero .acts{display:flex;flex-wrap:wrap;gap:8px}
.dx .live{display:inline-flex;align-items:center;font-size:11px;font-weight:650;letter-spacing:.05em;text-transform:uppercase;color:var(--dx-sub);background:#f1f5f9;border-radius:999px;padding:3px 10px}
.dx .switch{display:inline-flex;border:1px solid var(--dx-line);border-radius:8px;overflow:hidden;background:#fff}
.dx .switch a{padding:4px 11px;font-size:12px;font-weight:600;color:var(--dx-sub);text-decoration:none}.dx .switch a+a{border-left:1px solid var(--dx-line)}
.dx .switch a.on{background:var(--accent);color:#fff}
.dx .strip{display:flex;flex-wrap:wrap;gap:6px 22px;margin-top:14px;padding-top:12px;border-top:1px solid var(--dx-line);font-size:12.5px;color:var(--dx-sub)}
/* kpis */
.dx .kpis{display:grid;gap:14px;grid-template-columns:repeat(auto-fit,minmax(176px,1fr));margin:0 0 16px}
.dx.stack .kpis{grid-template-columns:1fr;margin:0 0 16px}
.dx .kpi{background:#fff;border:1px solid var(--dx-line);border-radius:16px;padding:14px 16px;box-shadow:0 1px 2px rgba(15,23,42,.03);display:block;color:inherit;text-decoration:none}
.dx a.kpi:hover{border-color:#cbd5e1;text-decoration:none}
.dx .kpi .top{display:flex;justify-content:space-between;align-items:center;gap:8px}
.dx table.q .pill{font-size:10.5px;padding:2px 7px}
.dx .kpi .val{font-size:30px;font-weight:700;line-height:1.1;margin:8px 0 2px}
.dx .kpi .val small{font-size:14px;font-weight:500;color:var(--dx-mute)}
.dx .kpi .note{font-size:12px;color:var(--dx-mute)}
.dx .kpi .foot{display:flex;justify-content:space-between;align-items:flex-end;gap:8px;margin-top:6px;font-size:12px;color:var(--dx-sub)}
.dx .bar{height:5px;background:#e2e8f0;border-radius:999px;overflow:hidden}.dx .bar i{display:block;height:100%;background:var(--accent);border-radius:999px}
/* lists */
.dx .alert-item{border:1px solid var(--dx-line);border-radius:12px;padding:12px 13px;margin-bottom:10px;background:#fff}
.dx .alert-item.red{background:#fffafa;border-color:#fecaca}
.dx .alert-item .meta{display:flex;justify-content:space-between;gap:8px;font-size:11px;color:var(--dx-mute);margin-bottom:6px}
.dx .alert-item p{margin:0 0 10px;font-size:13px;line-height:1.45}
.dx .alert-item .acts{display:flex;gap:8px;flex-wrap:wrap}
.dx .empty{color:var(--dx-mute);font-size:13px;padding:10px 0}
.dx .floor{display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(200px,1fr))}
.dx .fl{border:1px solid var(--dx-line);border-radius:12px;padding:12px 14px;display:block;color:inherit;text-decoration:none;background:#fff}
.dx a.fl:hover{border-color:#cbd5e1;text-decoration:none}
.dx .fl .k{display:flex;justify-content:space-between;align-items:center;margin-bottom:6px}
.dx .fl .n{font-weight:650;font-size:15px}.dx .fl .s{font-size:12px;color:var(--dx-mute);min-height:16px}
.dx .fl .m{font-size:12px;color:var(--dx-sub);margin-top:8px}
.dx table.q{width:100%;border-collapse:collapse;font-size:13px}
.dx table.q th{text-align:left;font-size:11px;font-weight:650;letter-spacing:.04em;text-transform:uppercase;color:var(--dx-mute);padding:8px;border-bottom:1px solid var(--dx-line);background:#fff}
.dx table.q td{padding:9px 8px;border-bottom:1px solid #f1f5f9;vertical-align:middle}
.dx table.q tr:hover td{background:#f8fafc}.dx table.q tr:last-child td{border-bottom:0}
.dx .thumb{width:34px;height:34px;border-radius:8px;object-fit:cover;border:1px solid var(--dx-line);background:#f1f5f9;flex:none;padding:0;min-height:0}
.dx .cell{display:flex;align-items:center;gap:10px}
.dx .sand{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.dx .sand div.t{border:1px solid var(--dx-line);border-radius:10px;padding:9px 11px}
.dx .sand .l{font-size:11px;color:var(--dx-mute)}.dx .sand .v{font-weight:650;font-size:15px;margin-top:2px}
.dx .tabs{display:inline-flex;background:#f1f5f9;border-radius:8px;padding:3px;gap:2px}
.dx .tabs button{border:0;background:transparent;padding:4px 11px;border-radius:6px;font-size:12px;font-weight:600;color:var(--dx-sub);cursor:pointer;min-height:0}
.dx .tabs button.on{background:#fff;color:var(--dx-ink);box-shadow:0 1px 2px rgba(15,23,42,.08)}
.dx .mini{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:10px;margin-top:12px}
.dx .mini>div{border:1px solid var(--dx-line);border-radius:10px;padding:9px 11px}
.dx .mini .l{font-size:11px;color:var(--dx-mute)}.dx .mini .v{font-size:18px;font-weight:700;margin-top:2px}
.dx .scan{display:flex;gap:8px}.dx .scan input:not(#_){flex:1;min-width:0;max-width:none;min-height:36px}
/* layouts */
.dx .g-command{display:grid;gap:16px;grid-template-columns:minmax(0,1fr) 340px;align-items:start}
.dx .g-columns{display:grid;gap:16px;grid-template-columns:290px minmax(0,1fr) 290px;align-items:start}
.dx .g-command>div,.dx .g-columns>div{min-width:0}
@media(max-width:1180px){.dx .g-columns{grid-template-columns:minmax(0,1fr) 300px}.dx .g-columns>div:nth-child(1){grid-column:1/-1}}
@media(max-width:900px){.dx .g-command,.dx .g-columns{grid-template-columns:1fr}.dx .hero h1{font-size:23px}}
</style>
