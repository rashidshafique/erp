@extends('layouts.app')

@section('title', 'Foundry size')
@section('breadcrumb', 'Settings / Foundry size')

@section('content')
<style>
    .fs-cards { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin-bottom: 16px; }
    .fs-card { display: block; margin: 0; cursor: pointer; }
    .fs-card input { position: absolute; opacity: 0; pointer-events: none; }
    .fs-card > span { display: flex; flex-direction: column; gap: 4px; padding: 14px 16px; border-radius: 8px; border: 1.5px solid #e6e7e9; background: #fff; height: 100%; }
    .fs-card input:checked + span { border-color: #2f5fd0; background: #eef2fc; }
    .fs-card input:focus-visible + span { outline: 2px solid #2f5fd0; outline-offset: 2px; }
    .fs-card .top { display: flex; justify-content: space-between; gap: 8px; }
    .fs-card .name { font-size: 14px; font-weight: 600; }
    .fs-card .tag { font-size: 11.5px; color: #6b6e73; }
    .fs-card .desc { font-size: 12.5px; color: #6b6e73; line-height: 1.45; }
    .fs-warn { display: none; gap: 12px; align-items: flex-start; padding: 12px 16px; border-radius: 8px; background: #fdf3e1; line-height: 1.5; margin-bottom: 16px; font-size: 13px; }
    .fs-warn.show { display: flex; }
    .fs-warn i { flex: none; margin-top: 5px; width: 8px; height: 8px; border-radius: 50%; background: #b8770f; }
    @media (max-width: 900px) { .fs-cards { grid-template-columns: 1fr; } }
</style>
<div class="page-heading">
    <div><h1>Foundry size</h1><p>Sets how much of the app is used. Size picks the menu and a starting process depth. Hidden screens still open by address, and every rule stays editable.</p></div>
    <div class="actions"><a class="btn" href="{{ route('settings.size.edit') }}">Discard</a><button class="btn btn-primary" type="submit" form="size-form">Save</button></div>
</div>
@if ($depth === 'CUSTOM')
    <p class="muted" style="margin-top:0">Rules are a custom mix; a size replaces them with its preset.</p>
@endif
<form id="size-form" method="POST" action="{{ route('settings.size') }}">
    @csrf @method('PUT')
    <div class="fs-cards">
        @foreach ($sizes as $key => $option)
            <label class="fs-card"><input type="radio" name="size" value="{{ $key }}" data-preset="{{ ucfirst(strtolower($option['preset'])) }}" @checked($current === $key)><span>
                <span class="top"><span class="name">{{ $option['label'] }}@if ($current === $key) <span class="status">In use</span>@endif</span><span class="tag">{{ ucfirst(strtolower($option['preset'])) }} depth</span></span>
                <span class="desc">{{ $option['description'] }}</span>
            </span></label>
        @endforeach
    </div>
    <div class="fs-warn" id="size-warn"><i></i><div><strong>Saving applies the <span id="size-preset"></span> preset.</strong> It resets the common process rules on <a href="{{ route('settings.pages.melting') }}">Melting &amp; pouring</a>, <a href="{{ route('settings.pages.orders') }}">Order &amp; pricing rules</a> and the other rule pages. Rules you changed by hand are replaced. Modules are not switched off. The menu changes too.</div></div>
</form>
<p class="muted small">Switch single modules on or off under <a href="{{ route('settings.modules.edit') }}">Modules</a>.</p>
<script>
(function () {
    var radios = document.querySelectorAll('#size-form input[name=size]');
    var cur = '{{ $current }}';
    function sync() {
        var on = document.querySelector('#size-form input[name=size]:checked');
        var warn = document.getElementById('size-warn');
        warn.classList.toggle('show', !!on && on.value !== cur);
        if (on) document.getElementById('size-preset').textContent = on.dataset.preset;
    }
    radios.forEach(function (r) { r.addEventListener('change', sync); });
    sync();
})();
</script>
@endsection
