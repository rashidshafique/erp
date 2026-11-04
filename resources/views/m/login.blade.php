@extends('m.layout')
@section('title', 'Sign in')
@section('body')
@php $b = \App\Support\UiSettings::brand(); @endphp
<form method="POST" action="{{ route('login.attempt') }}" style="flex:1;display:flex;flex-direction:column;justify-content:center;gap:14px;padding:24px 20px">@csrf
    <input type="hidden" name="mobile" value="1">
    <div style="margin-bottom:12px">
        @if($b['logo'])<img src="{{ $b['logo'] }}" alt="{{ $b['name'] }}" style="display:block;max-width:100%;height:{{ min($b['height'], 48) }}px;object-fit:contain;margin-bottom:14px">@endif
        <div class="cap">{{ $b['name'] }} · Shop floor</div>
        <div style="font-size:26px;font-weight:600;margin-top:2px">Sign in</div>
    </div>
    <label style="display:flex;flex-direction:column;gap:6px;font-size:13px" class="muted">Email<input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" inputmode="email"></label>
    <label style="display:flex;flex-direction:column;gap:6px;font-size:13px" class="muted">Password<input type="password" name="password" required autocomplete="current-password"></label>
    <label style="display:flex;align-items:center;gap:8px;font-size:14px"><input type="checkbox" name="remember" value="1" checked style="width:20px;height:20px"> Keep me signed in on this phone</label>
    @error('email')<div class="err">{{ $message }}</div>@enderror
    <button type="submit" class="btn" style="margin-top:6px">Sign in</button>
</form>
<div id="install" hidden style="margin:0 20px 24px;padding:12px 14px;border-radius:14px;background:#f8f8f9;border:1px solid #d9dbde;font-size:13.5px">
    <div class="cap" style="padding-bottom:4px">Install on this phone</div>
    <div id="install-ios" hidden>Tap <b>Share</b>, then <b>Add to Home Screen</b>, then <b>Add</b>. It opens full screen and keeps working without signal.</div>
    <div id="install-android" hidden><button type="button" id="install-btn" class="chip" style="font:inherit;font-size:14px;font-weight:600;color:#2f5fd0;cursor:pointer">Install app</button> <span class="muted">Opens full screen and keeps working without signal.</span></div>
</div>
<script>
(function () {
    var standalone = matchMedia('(display-mode: standalone)').matches || navigator.standalone;
    if (standalone) { return; }
    var box = document.getElementById('install'), ua = navigator.userAgent, saved;
    var ios = /iPhone|iPad|iPod/.test(ua) && /Safari/.test(ua) && !/CriOS|FxiOS|EdgiOS/.test(ua);
    if (ios) { box.hidden = false; document.getElementById('install-ios').hidden = false; }
    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault(); saved = e; box.hidden = false; document.getElementById('install-android').hidden = false;
    });
    document.getElementById('install-btn').addEventListener('click', function () {
        if (saved) { saved.prompt(); saved.userChoice.then(function () { box.hidden = true; }); }
    });
    window.addEventListener('appinstalled', function () { box.hidden = true; });
})();
</script>
@endsection
