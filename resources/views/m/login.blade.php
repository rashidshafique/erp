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
@endsection
