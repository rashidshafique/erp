@extends('layouts.app')
@section('title', 'Settings')
@section('breadcrumb', 'Settings')
@section('content')
<style>
    .sh-page { display: flex; flex-direction: column; gap: 20px; }
    .sh-search { display: flex; align-items: center; gap: 8px; width: 340px; max-width: 100%; padding: 7px 12px; border: 1px solid #d9dbde; border-radius: 6px; background: #fff; }
    .sh-search input { flex: 1; border: 0; outline: 0; background: transparent; font: inherit; font-size: 13px; padding: 0; min-width: 0; box-shadow: none; }
    .sh-search kbd { font-size: 11px; border: 1px solid #e6e7e9; border-radius: 4px; padding: 0 5px; color: #8a8d92; font-family: inherit; }
    .sh-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; }
    .sh-area { border: 1px solid #e6e7e9; border-radius: 8px; padding: 16px 18px; display: flex; flex-direction: column; gap: 12px; background: #fff; }
    .sh-area h2 { font-size: 16px; font-weight: 600; margin: 0; }
    .sh-pages { display: flex; flex-direction: column; gap: 3px; font-size: 14px; flex: 1; }
    .sh-pages a { display: flex; flex-direction: column; gap: 2px; margin: 0 -8px; padding: 6px 8px; border-radius: 6px; transition: background .12s; text-decoration: none; }
    .sh-pages a:hover { background: #eef2fc; }
    .sh-pages a b { font-weight: 500; color: #2f5fd0; }
    .sh-pages a span { color: #55585d; font-size: 13px; line-height: 1.4; }
    .sh-perm { padding-top: 10px; border-top: 1px solid #eff0f2; font-size: 12.5px; color: #55585d; }
    .sh-dev { border: 1px solid #e6e7e9; border-radius: 8px; background: #fff; }
    .sh-dev-head { display: flex; justify-content: space-between; align-items: baseline; padding: 12px 16px; border-bottom: 1px solid #e6e7e9; }
    .sh-dev-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); font-size: 14px; }
    .sh-dev-grid > div { padding: 12px 16px; border-bottom: 1px solid #eff0f2; border-right: 1px solid #eff0f2; display: flex; justify-content: space-between; gap: 10px; }
    .sh-dev-grid > div:nth-child(3n) { border-right: 0; }
    .sh-dev-grid > div:nth-last-child(-n+3) { border-bottom: 0; }
    @media (max-width: 1100px) { .sh-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .sh-dev-grid { grid-template-columns: 1fr; } .sh-dev-grid > div { border-right: 0; } }
    @media (max-width: 640px) { .sh-grid { grid-template-columns: 1fr; } }
</style>
<div class="sh-page">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap">
        <div><div style="font-size:22px;font-weight:600">Settings</div><div style="font-size:14px;color:#55585d;margin-top:4px">{{ $summary }}</div></div>
        <label class="sh-search"><input type="search" id="sh-q" placeholder='Search settings or keys, e.g. "fade", "pouring.gate"' autocomplete="off" aria-label="Search settings"><kbd>/</kbd></label>
    </div>

    @unless ($loginRequired)
    <div style="display:flex;gap:12px;align-items:flex-start;padding:12px 16px;border-radius:8px;background:#fdf3e1"><span style="flex:none;margin-top:5px;width:8px;height:8px;border-radius:50%;background:#b8770f"></span><div style="font-size:14px;line-height:1.5"><span style="font-weight:600">Sign-in is off.</span> Anyone at the screen can change every setting. Your developer can turn on <code>FOUNDRY_REQUIRE_LOGIN</code> so these pages use roles and permissions.</div></div>
    @endunless

    <div class="sh-grid">
        @foreach ($areas as $name => $area)
        <div class="sh-area" data-area>
            <h2>{{ $name }}</h2>
            <div class="sh-pages">
                @foreach ($area['pages'] as $p)
                <a href="{{ $p['href'] }}" data-page="{{ strtolower($p['name'].' '.$p['val']) }}"><b>{{ $p['name'] }}</b><span>{{ $p['val'] }}</span></a>
                @endforeach
            </div>
            <div class="sh-perm">Needs <code>{{ $area['perm'] }}</code></div>
        </div>
        @endforeach
    </div>

    <div class="sh-dev">
        <div class="sh-dev-head"><span style="font-size:15px;font-weight:600">Set by your developer</span><span style="font-size:13px;color:#55585d">Read-only here. Changed in the server config.</span></div>
        <div class="sh-dev-grid">
            @foreach ($developer as $label => [$value, $warn])
            <div><span>{{ $label }}</span><span @if ($warn) style="color:#b8770f;font-weight:500" @endif>{{ $value }}</span></div>
            @endforeach
        </div>
    </div>
</div>
<script>
(function () {
    var q = document.getElementById('sh-q');
    q.addEventListener('input', function () {
        var t = q.value.trim().toLowerCase();
        document.querySelectorAll('[data-area]').forEach(function (area) {
            var any = false;
            area.querySelectorAll('[data-page]').forEach(function (a) {
                var show = !t || a.dataset.page.indexOf(t) !== -1;
                a.style.display = show ? '' : 'none';
                any = any || show;
            });
            area.style.display = any ? '' : 'none';
        });
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === '/' && !/^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName)) { e.preventDefault(); q.focus(); }
    });
})();
</script>
@endsection
