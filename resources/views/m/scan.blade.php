@extends('m.layout')
@section('title', 'Scan label')
@section('body')
<div style="padding:14px 20px 6px"><a href="{{ route('m.home') }}" class="blue" style="font-size:14px;text-decoration:none">‹ Today</a>
    <div style="font-size:22px;font-weight:600;margin-top:4px">Scan label</div>
    <div class="muted" style="font-size:13px">Type the code, or use a scanner.</div></div>
<form method="GET" action="{{ route('m.scan') }}" style="padding:8px 20px;display:flex;flex-direction:column;gap:10px">
    <input name="code" value="{{ $code }}" autofocus autocomplete="off" autocapitalize="characters" placeholder="LOT-26-1214, HT-4421" style="font:inherit;font-size:18px;padding:14px;border:1px solid #d9dbde;border-radius:12px">
    @if($missing)<div style="color:#b00020;font-size:14px">Nothing found for “{{ $code }}”.</div>@endif
    <button class="chip on" style="font:inherit;font-size:16px;padding:14px;cursor:pointer">Open</button>
</form>
@endsection
