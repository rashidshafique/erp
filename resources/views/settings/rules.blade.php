@extends('layouts.app')

@section('title', 'Process rules')
@section('breadcrumb', 'Settings / Process rules')

@section('content')
<div class="page-heading">
    <div><h1>Process rules</h1><p>Start from a preset, then change any rule.</p></div>
    <div class="actions"><a class="btn" href="{{ route('settings.process-setup.edit') }}">Answer a few questions instead</a></div>
</div>
@if ($errors->any())<div class="alert alert-error">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif

<div class="panel"><div class="panel-body">
    <div class="rule-grid">
        @foreach ($presets as $key => [$label, $description])
            <form method="POST" action="{{ route('settings.rules.update') }}" @if ($current !== $key) onsubmit="return confirm('Apply {{ $label }}? This replaces the rules below.')" @endif>
                @csrf @method('PUT')
                <input type="hidden" name="preset" value="{{ $key }}">
                <h2 style="margin-top:0">{{ $label }} @if ($current === $key)<span class="status">In use</span>@endif</h2>
                <p>{{ $description }}</p>
                <button class="btn {{ $current === $key ? '' : 'btn-primary' }}" type="submit">{{ $current === $key ? 'Re-apply' : 'Use '.$label }}</button>
            </form>
        @endforeach
    </div>
    @if ($current === 'CUSTOM')<p class="muted small" style="margin-bottom:0">Now a custom mix.</p>@endif
</div></div>

{{-- Each section is a tab (partials.tabs); /settings/rules#flasks opens that tab. Save sends every tab. --}}
<form method="POST" action="{{ route('settings.rules.update') }}">
    @csrf @method('PUT')
    <div data-tab-host>
    @foreach ($sections as $section => $rules)
        <div class="tab-marker" data-tab-start="{{ $section }}" data-tab-block="{{ collect(array_keys($rules))->filter(fn ($key) => $errors->has($key))->count() }}"></div>
        <div class="panel" id="{{ \Illuminate\Support\Str::slug($section) }}-rules">
            <div class="panel-body"><div class="rule-grid">
                @foreach ($rules as $key => $rule)
                    <fieldset>
                        <legend>{{ $rule['label'] }}</legend>
                        @if ($rule['options'] === null)
                            <input type="number" name="{{ $key }}" value="{{ old($key, $rule['value']) }}" min="0" step="any" style="max-width:140px" aria-label="{{ $rule['label'] }}">
                        @else
                            @foreach ($rule['options'] as $value => $text)<label><input type="radio" name="{{ $key }}" value="{{ $value }}" @checked((string) old($key, $rule['value']) === (string) $value)> {{ $text }}</label>@endforeach
                        @endif
                        @if (in_array($key, $waiting, true))<p class="muted small">Needs Quick flow; the first choice applies until then.</p>@endif
                    </fieldset>
                @endforeach
            </div></div>
        </div>
    @endforeach
    </div>
    <div class="actions"><button class="btn btn-primary" type="submit">Save rules</button></div>
</form>
@endsection
