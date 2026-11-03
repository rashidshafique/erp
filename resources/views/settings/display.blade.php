{{-- Appearance & formats, design 22c: one form with Screen, Brand and Formats cards, a live Preview on the right and one Save. --}}
@extends('layouts.app')
@section('title', 'Appearance')
@section('breadcrumb', 'Settings / Company / Appearance')
@section('content')
@php
    use App\Support\Formats;
    use App\Support\UiSettings;
    $fmt = Formats::all();
    $brand = UiSettings::brand();
    $theme = UiSettings::theme();
    $fontNow = UiSettings::font();
    $canFont = UiSettings::canEditFont();
    $dash = \App\Models\AppSetting::getValue('ui.dashboard', 'classic');
    $dash = in_array($dash, UiSettings::DASHBOARD_LAYOUTS, true) ? $dash : 'classic';
    $accentNow = old('accent', $theme['accent']);
    $sampleDate = \Illuminate\Support\Carbon::parse('2026-10-18 14:30:15');
    $dashLabels = ['classic' => 'Classic', 'command' => 'Command', 'columns' => 'Columns', 'telemetry' => 'Telemetry'];
    $swatches = ['#24945a' => 'Green', '#2f5fd0' => 'Blue', '#a84a22' => 'Rust', '#7c3aed' => 'Violet', '#0f766e' => 'Teal', '#b45309' => 'Amber', '#be123c' => 'Crimson', '#334155' => 'Slate'];
@endphp
<style>
    .ap-bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; padding: 14px 28px; border-bottom: 1px solid var(--border); font-size: 13px; }
    .ap-grid { display: grid; grid-template-columns: minmax(0, 1fr) 340px; }
    .ap-main { padding: 24px 28px; display: flex; flex-direction: column; gap: 18px; font-size: 13px; min-width: 0; }
    .ap-side { padding: 24px; background: #fafafa; border-left: 1px solid var(--border); display: flex; flex-direction: column; gap: 12px; font-size: 13px; }
    .ap-card { border: 1px solid var(--border); border-radius: 8px; }
    .ap-card > h2 { margin: 0; padding: 11px 16px; border-bottom: 1px solid var(--border); font-size: 13.5px; font-weight: 600; }
    .ap-row { display: grid; grid-template-columns: 200px minmax(0, 1fr); gap: 16px; align-items: center; padding: 12px 16px; border-bottom: 1px solid #eff0f2; }
    .ap-row:last-child { border-bottom: 0; }
    .ap-row > .l { font-weight: 500; } .ap-row .s { font-size: 12px; color: var(--muted); font-weight: 400; display: block; }
    .ap-row select, .ap-row input[type=text], .ap-row input[type=number] { padding: 6px 10px; border: 1px solid #d9dbde; border-radius: 6px; width: auto; min-height: 0; }
    .ap-seg { display: inline-flex; width: fit-content; border: 1px solid #d9dbde; border-radius: 6px; overflow: hidden; }
    .ap-seg label { margin: 0; cursor: pointer; } .ap-seg input { position: absolute; opacity: 0; pointer-events: none; }
    .ap-seg span { display: block; padding: 6px 12px; border-right: 1px solid var(--border); font-size: 12.5px; background: #fff; color: var(--text); }
    .ap-seg label:last-child span { border-right: 0; }
    .ap-seg input:checked + span { background: var(--accent); color: #fff; }
    .ap-seg input:focus-visible + span { outline: 2px solid var(--accent); outline-offset: -2px; }
    .ap-sw { width: 26px; height: 26px; border-radius: 6px; border: 2px solid transparent; padding: 0; cursor: pointer; } .ap-sw.on { border-color: #1c1d1f; }
    .ap-note { font-size: 12px; color: #8a8d92; }
    .ap-btn { padding: 7px 14px; border-radius: 6px; background: #fff; border: 1px solid #d9dbde; font-size: 13px; color: var(--text); text-decoration: none !important; cursor: pointer; }
    .ap-btn.primary { background: var(--accent); border-color: var(--accent); color: #fff; font-weight: 500; }
    @media (max-width: 1100px) { .ap-grid { grid-template-columns: 1fr; } .ap-side { border-left: 0; border-top: 1px solid var(--border); } .ap-row { grid-template-columns: 1fr; gap: 6px; } }
</style>
<form id="ap-form" method="POST" action="{{ route('settings.display.update') }}" enctype="multipart/form-data" style="border:1px solid var(--border);border-radius:10px;background:var(--surface,#fff)">@csrf @method('PUT')
    <div class="ap-bar">
        <div style="display:flex;gap:8px;align-items:center"><a href="{{ route('settings.home') }}" style="color:var(--muted)">Settings</a><span style="color:var(--muted)">/</span><span style="color:var(--muted)">Company</span><span style="color:var(--muted)">/</span><span style="font-weight:600">Appearance</span></div>
        <div style="display:flex;gap:8px"><a class="ap-btn" href="{{ route('settings.display') }}">Discard</a><button class="ap-btn primary" type="submit">Save</button></div>
    </div>
    <div class="ap-grid">
        <div class="ap-main">
            <div><h1 style="font-size:22px;font-weight:600;margin:0">Appearance</h1><div style="color:var(--muted);margin-top:4px">How the app looks for everyone. People can pick their own dashboard layout.</div><div class="ap-note" style="margin-top:6px">Needs <code>settings.manage</code> · font: administrator only</div></div>
            @if ($errors->any())<div class="alert alert-error">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif

            <div class="ap-card">
                <h2>Screen</h2>
                <div class="ap-row"><span class="l">Density</span><div class="ap-seg">
                    <label><input type="radio" name="density" value="COMPACT" @checked($ui['compact'])><span>Compact</span></label><label><input type="radio" name="density" value="COMFORTABLE" @checked(! $ui['compact'])><span>Comfortable</span></label></div></div>
                <div class="ap-row"><div class="l">Dashboard layout<span class="s">Default, each person can change</span></div><div class="ap-seg">
                    @foreach ($dashLabels as $k => $l)<label><input type="radio" name="dashboard" value="{{ $k }}" @checked($dash === $k)><span>{{ $l }}</span></label>@endforeach</div></div>
                <div class="ap-row"><span class="l">Font</span>
                    @if ($canFont)
                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                        <select name="font" id="ui-font" style="width:260px">@foreach (UiSettings::FONTS as $k => $f)<option value="{{ $k }}" @selected(old('font', $fontNow['key']) === $k)>{{ $f['label'] }}</option>@endforeach<option value="custom" @selected(old('font', $fontNow['key']) === 'custom')>Another font (type its name)</option></select>
                        <input type="text" name="custom_font" id="ui-font-custom" maxlength="60" value="{{ old('custom_font', $fontNow['name'] ?? '') }}" placeholder="e.g. Calibri" aria-label="Font name" style="width:180px" @if (old('font', $fontNow['key']) !== 'custom') hidden @endif>
                        <span class="ap-note">10 presets or a custom font · administrator only</span>
                    </div>
                    @else
                    <span class="ap-note">{{ $fontNow['label'] }}, set by the administrator</span>
                    @endif
                </div>
                <div class="ap-row"><span class="l">Accent</span>
                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                        @foreach ($swatches as $hex => $name)<button type="button" class="ap-sw" data-hex="{{ $hex }}" title="{{ $name }}" aria-label="{{ $name }}" style="background:{{ $hex }}"></button>@endforeach
                        <input type="color" id="accent-picker" value="{{ $accentNow }}" aria-label="Pick another colour" style="width:34px;height:28px;padding:1px">
                        <input type="text" name="accent" id="accent-hex" value="{{ $accentNow }}" maxlength="7" aria-label="Accent colour" style="width:84px;font-family:ui-monospace,Menlo,monospace;font-size:12px">
                        <input type="hidden" name="reset_accent" value="0">
                        <span id="accent-msg" style="font-size:12px"></span>
                    </div></div>
                <div class="ap-row" style="grid-template-columns:1fr;display:flex;gap:28px;flex-wrap:wrap">
                    <label style="display:flex;gap:6px;align-items:center;margin:0"><input type="checkbox" name="table_toolbar" value="1" @checked($ui['toolbar'])> Table toolbar</label>
                    <label style="display:flex;gap:6px;align-items:center;margin:0"><input type="checkbox" name="tidy_forms" value="1" @checked($ui['tidy_forms'])> Tidy forms</label>
                    <label style="display:flex;gap:6px;align-items:center;margin:0"><input type="checkbox" name="row_click" value="1" @checked(\App\Models\AppSetting::getValue('ui.row_click', '1') === '1')> Open record on row click</label>
                </div>
            </div>

            <div class="ap-card">
                <h2>Brand</h2>
                <div class="ap-row"><span class="l">Brand name</span><input type="text" name="brand_name" maxlength="40" required value="{{ old('brand_name', $brand['name']) }}" style="width:260px"></div>
                <div class="ap-row"><span class="l">Logo</span>
                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                        @if ($brand['logo'])<img src="{{ $brand['logo'] }}" alt="Current logo" style="height:40px;max-width:160px;object-fit:contain;border:1px solid var(--border);padding:3px;border-radius:6px">
                        @else<div style="width:120px;height:40px;border:1px dashed #c4c7cc;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:12px;color:#8a8d92">No logo</div>@endif
                        <label class="ap-btn" style="margin:0;font-size:12.5px;padding:6px 12px">Upload<input type="file" name="logo" accept=".png,.jpg,.jpeg,.webp,.svg" hidden onchange="this.parentNode.nextElementSibling.textContent=this.files[0]?.name||''"></label><span class="ap-note"></span>
                        @if ($brand['logo'])<label style="margin:0;display:flex;gap:6px;align-items:center"><input type="checkbox" name="remove_logo" value="1"> Remove</label>@endif
                        <span class="ap-note">PNG, JPG, WEBP or SVG, up to 1 MB</span>
                    </div></div>
                <div class="ap-row"><span class="l">Logo position · height</span>
                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap"><div class="ap-seg">
                        <label><input type="radio" name="logo_position" value="TOP_LEFT" @checked(old('logo_position', $brand['position']) === 'TOP_LEFT')><span>Top left</span></label><label><input type="radio" name="logo_position" value="BOTTOM_LEFT" @checked(old('logo_position', $brand['position']) === 'BOTTOM_LEFT')><span>Bottom left</span></label></div>
                        <input type="number" name="logo_height" min="20" max="120" value="{{ old('logo_height', $brand['height']) }}" style="width:70px;text-align:right" aria-label="Logo height in pixels"><span style="font-size:12.5px;color:var(--muted)">px (20–120)</span></div></div>
            </div>

            <div class="ap-card">
                <h2>Formats</h2>
                <div class="ap-row"><span class="l">Date · time</span><div style="display:flex;gap:10px;flex-wrap:wrap">
                    <select name="date" id="f-date" style="width:230px">@foreach (Formats::DATES as $k => $f)<option value="{{ $k }}" data-sample="{{ $sampleDate->format($f['date']) }}" @selected($fmt['date'] === $k)>{{ $f['label'] }}</option>@endforeach</select>
                    <select name="time" id="f-time" style="width:240px">@foreach (Formats::TIMES as $k => $f)<option value="{{ $k }}" data-sample="{{ $sampleDate->format($f['time']) }}" @selected($fmt['time'] === (string) $k)>{{ $f['label'] }}</option>@endforeach</select></div></div>
                <div class="ap-row"><span class="l">Numbers</span><div class="ap-seg" id="f-number">
                    @foreach (Formats::NUMBERS as $k => $f)<label><input type="radio" name="number" value="{{ $k }}" data-dec="{{ $f['dec'] }}" data-thou="{{ $f['thou'] }}" @checked($fmt['number'] === $k)><span>{{ $f['label'] }}</span></label>@endforeach</div></div>
                <div class="ap-row"><span class="l">Currency</span><div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                    <input type="text" name="currency" id="f-cur" maxlength="8" value="{{ old('currency', $fmt['currency']) }}" placeholder="PKR" aria-label="Currency symbol or code" style="width:80px">
                    <div class="ap-seg"><label><input type="radio" name="currency_position" value="before" @checked($fmt['currency_position'] === 'before')><span>Before</span></label><label><input type="radio" name="currency_position" value="after" @checked($fmt['currency_position'] === 'after')><span>After</span></label></div></div></div>
                <div class="ap-row"><span class="l">Decimals</span><div style="display:flex;align-items:center;gap:10px"><select name="decimals" id="f-dec" style="width:70px">@foreach (Formats::DECIMALS as $d)<option value="{{ $d }}" @selected($fmt['decimals'] === $d)>{{ $d }}</option>@endforeach</select><span style="font-size:12.5px;color:var(--muted)">0 to 6 · the most places a typed number keeps. Only display changes; records are untouched.</span></div></div>
            </div>
        </div>
        <aside class="ap-side">
            <span style="font-size:11px;font-weight:600;color:#8a8d92;text-transform:uppercase;letter-spacing:.06em">Preview</span>
            <div style="background:#fff;border:1px solid var(--border);border-radius:8px;padding:16px;display:flex;flex-direction:column;gap:12px">
                <div style="display:flex;justify-content:space-between;align-items:baseline"><span style="font-weight:600">Invoice total</span><code style="color:var(--muted)">IN-26-0412</code></div>
                <div id="pv-num" style="font-size:22px;font-weight:600"></div>
                <div id="pv-due" style="font-size:12px;color:var(--muted)"></div>
                <button type="button" id="pv-btn" style="padding:8px 14px;border-radius:6px;border:0;color:#fff;font-weight:500;background:var(--accent)">Send invoice</button>
            </div>
            <div class="ap-note">Changes show here before you save.</div>
        </aside>
    </div>
</form>
<script>
(function () {
    var $ = function (id) { return document.getElementById(id); };
    var pick = $('accent-picker'), hex = $('accent-hex'), msg = $('accent-msg');
    function lum(h) { var c = [1, 3, 5].map(function (i) { var v = parseInt(h.substr(i, 2), 16) / 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); }); return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2]; }
    function accent(v) {
        var ok = /^#[0-9a-f]{6}$/i.test(v);
        document.querySelectorAll('.ap-sw').forEach(function (b) { b.classList.toggle('on', ok && b.dataset.hex.toLowerCase() === v.toLowerCase()); });
        if (! ok) { msg.textContent = 'Use a colour like #24945a'; msg.style.color = '#b3352c'; return; }
        hex.value = v; pick.value = v;
        var ratio = 1.05 / (lum(v) + 0.05);
        msg.textContent = ratio >= 3 ? 'Good contrast with white text (' + ratio.toFixed(1) + ':1)' : 'Too light for white text (' + ratio.toFixed(1) + ':1), pick a darker one';
        msg.style.color = ratio >= 3 ? '#1d7048' : '#b3352c';
        document.documentElement.style.setProperty('--accent', v, 'important');
    }
    pick.addEventListener('input', function () { accent(pick.value); });
    hex.addEventListener('input', function () { accent(hex.value); });
    document.querySelectorAll('.ap-sw').forEach(function (b) { b.addEventListener('click', function () { accent(b.dataset.hex); }); });
    accent(hex.value);

    var font = $('ui-font'), custom = $('ui-font-custom');
    if (font && custom) { font.addEventListener('change', function () { custom.hidden = font.value !== 'custom'; }); }

    function group(int, sep) { return sep === '' ? int : int.replace(/\B(?=(\d{3})+(?!\d))/g, sep); }
    function preview() {
        var n = document.querySelector('#f-number input:checked'), places = parseInt($('f-dec').value, 10), cur = $('f-cur').value.trim();
        var parts = (1234567.891).toFixed(places).split('.');
        var amount = group(parts[0], n.dataset.thou) + (parts[1] ? n.dataset.dec + parts[1] : '');
        var before = document.querySelector('[name=currency_position]:checked').value === 'before';
        $('pv-num').textContent = cur ? (before ? cur + ' ' + amount : amount + ' ' + cur) : amount;
        $('pv-due').textContent = 'Due ' + $('f-date').selectedOptions[0].dataset.sample + ' · ' + $('f-time').selectedOptions[0].dataset.sample;
    }
    document.querySelectorAll('#f-date, #f-time, #f-dec, #f-cur, #f-number input, [name=currency_position]').forEach(function (el) { el.addEventListener('input', preview); el.addEventListener('change', preview); });
    preview();
})();
</script>
@endsection
