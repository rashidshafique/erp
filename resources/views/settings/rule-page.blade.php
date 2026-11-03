@extends('layouts.app')

@section('title', $info['title'])
@section('breadcrumb', 'Settings / '.$info['title'])

@section('content')
<style>
    .rp-switch { display: flex; align-items: center; gap: 8px; font-size: 13px; white-space: nowrap; color: inherit; text-decoration: none; }
    .rp-switch i { width: 30px; height: 18px; border-radius: 999px; position: relative; background: #c9ccd1; flex: none; }
    .rp-switch i b { position: absolute; top: 2px; left: 2px; width: 14px; height: 14px; border-radius: 50%; background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.2); }
    .rp-switch.on i { background: #2f5fd0; }
    .rp-switch.on i b { left: 14px; }
    .rp-group { border: 1px solid #e6e7e9; border-radius: 8px; margin-bottom: 18px; background: #fff; }
    .rp-group h2 { margin: 0; padding: 11px 16px; border-bottom: 1px solid #e6e7e9; font-size: 13.5px; font-weight: 600; }
    .rp-row { display: grid; grid-template-columns: minmax(0, 1fr) 340px 150px; gap: 20px; align-items: center; padding: 12px 16px; border-bottom: 1px solid #eff0f2; font-size: 13px; }
    .rp-row:last-child { border-bottom: 0; }
    .rp-row code { font-size: 11px; color: #a0a3a8; }
    .rp-seg { display: inline-flex; flex-wrap: wrap; border: 1px solid #d9dbde; border-radius: 6px; overflow: hidden; }
    .rp-seg label { margin: 0; cursor: pointer; }
    .rp-seg input { position: absolute; opacity: 0; pointer-events: none; }
    .rp-seg span { display: block; padding: 6px 12px; border-right: 1px solid #e6e7e9; font-size: 12.5px; background: #fff; color: #55585d; }
    .rp-seg label:last-child span { border-right: 0; }
    .rp-seg input:checked + span { background: #2f5fd0; color: #fff; }
    .rp-seg input:focus-visible + span { outline: 2px solid #2f5fd0; outline-offset: -2px; }
    .rp-def { text-align: right; font-size: 12px; color: #8a8d92; }
    .rp-def button { border: 0; background: transparent; color: #2f5fd0; padding: 0; font-size: 12px; cursor: pointer; }
    @media (max-width: 900px) { .rp-row { grid-template-columns: 1fr; gap: 8px; } .rp-def { text-align: left; } }
</style>
<div class="page-heading">
    <div>
        <h1>{{ $info['title'] }}</h1>
        <p>{{ $info['purpose'] }}</p>
        <p class="muted small"><a href="{{ route('settings.process-setup.edit') }}">How you work</a> and the presets on <a href="{{ route('settings.rules') }}">Process rules</a> set the common rules too</p>
    </div>
    <div class="actions">
        <a class="rp-switch {{ $advanced ? 'on' : '' }}" href="{{ route('settings.pages.'.$page, $advanced ? [] : ['advanced' => 1]) }}"><i><b></b></i>Show all rules ({{ $total }})</a>
        <a class="btn" href="{{ url()->current().($advanced ? '?advanced=1' : '') }}">Discard</a>
        <button class="btn btn-primary" type="submit" form="rule-page-form">Save</button>
    </div>
</div>
@if ($errors->any())<div class="alert alert-error">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif

<form id="rule-page-form" method="POST" action="{{ route('settings.rules.update') }}">
    @csrf @method('PUT')
    @forelse ($sections as $section => $rules)
        <div class="rp-group">
            <h2>{{ $section }}</h2>
            @foreach ($rules as $key => $rule)
                @php($changed = (string) $rule['value'] !== (string) $rule['default'])
                <div class="rp-row">
                    <div style="min-width:0">
                        <div style="font-weight:500">@if ($n = \App\Support\ProcessDepth::number($key))<span class="rule-no" title="Also set by How you work and the presets">{{ $n }}</span>@endif{{ $rule['label'] }}</div>
                        <code>{{ $rule['key'] }}</code>
                        @if (in_array($key, $waiting, true))<div class="muted small">Needs Quick flow; the first choice applies until then.</div>@endif
                    </div>
                    <div>
                        @if ($rule['options'] === null)
                            <input type="number" name="{{ $key }}" value="{{ old($key, $rule['value']) }}" min="0" step="any" style="width:100px;text-align:right" aria-label="{{ $rule['label'] }}">
                        @else
                            <div class="rp-seg" role="radiogroup" aria-label="{{ $rule['label'] }}">
                                @foreach ($rule['options'] as $value => $text)
                                    <label title="{{ $text }}"><input type="radio" name="{{ $key }}" value="{{ $value }}" @checked((string) old($key, $rule['value']) === (string) $value)><span>{{ \Illuminate\Support\Str::before($text, ':') }}</span></label>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <div class="rp-def">
                        @if ($changed)
                            Default {{ $rule['options'] === null ? $rule['default'] : \Illuminate\Support\Str::before($rule['options'][$rule['default']] ?? $rule['default'], ':') }} ·
                            <button type="submit" form="reset-{{ $key }}">Reset</button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @empty
        <p class="muted">No rules here are switched on for this foundry.</p>
    @endforelse
</form>
@foreach ($sections as $rules)
    @foreach ($rules as $key => $rule)
        @if ((string) $rule['value'] !== (string) $rule['default'])
            <form id="reset-{{ $key }}" method="POST" action="{{ route('settings.rules.reset') }}">@csrf<input type="hidden" name="rule" value="{{ $key }}"></form>
        @endif
    @endforeach
@endforeach
@endsection
