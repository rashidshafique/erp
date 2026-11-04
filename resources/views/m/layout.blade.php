@php $b = \App\Support\UiSettings::brand(); @endphp
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#ffffff">
<title>@yield('title', $b['name'])</title>
<script>try{localStorage.setItem('erp.phone','1')}catch(e){}</script>
<style>
*{box-sizing:border-box}body{margin:0;font-family:system-ui,-apple-system,sans-serif;color:#1c1d1f;background:#fff;-webkit-text-size-adjust:100%}
.wrap{max-width:480px;margin:0 auto;min-height:100vh;display:flex;flex-direction:column;padding:env(safe-area-inset-top) 0 env(safe-area-inset-bottom)}
.cap{font-size:12px;font-weight:600;color:#55585d;text-transform:uppercase;letter-spacing:.06em}
.muted{color:#55585d}.blue{color:#2f5fd0}code{font-family:ui-monospace,Menlo,monospace;font-size:.92em}
.tile{display:flex;flex-direction:column;justify-content:center;gap:2px;min-height:60px;padding:8px 16px;border-radius:14px;background:#fff;border:1px solid #d9dbde;color:inherit;text-decoration:none}
.tile b{font-size:16px;font-weight:600}.tile span{font-size:12.5px;color:#55585d}
.btn{display:block;width:100%;height:52px;border:0;border-radius:14px;background:#2f5fd0;color:#fff;font:600 16px system-ui;cursor:pointer}
.row{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:12px 0;border-bottom:1px solid #eff0f2;color:inherit;text-decoration:none}
.chip{display:inline-block;padding:7px 12px;border-radius:999px;border:1px solid #d9dbde;font-size:13px;color:#3d4045;text-decoration:none;background:#fff}
.chip.on{background:#1c1d1f;border-color:#1c1d1f;color:#fff}
input[type=email],input[type=password]{width:100%;height:52px;padding:0 14px;border:1px solid #d9dbde;border-radius:12px;font-size:16px}
.err{color:#b00020;font-size:13px}
</style></head><body><div class="wrap">@yield('body')</div><script src="{{ asset('js/phone-queue.js') }}" defer></script></body></html>
