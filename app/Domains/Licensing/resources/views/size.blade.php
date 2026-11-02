@extends('layouts.app')

@section('title', 'Foundry size')
@section('breadcrumb', 'Settings / Foundry size')

@section('content')
<div class="page-heading">
    <div><h1>Foundry size</h1><p>Sets the menu and the process rules preset. Hidden screens still open by address, and every rule stays editable.</p></div>
</div>
<div class="panel"><div class="panel-body">
    @if ($depth === 'CUSTOM')
        <p class="muted" style="margin-top:0">Rules are a custom mix; a size replaces them with its preset.</p>
    @endif
    <div class="rule-grid">
        @foreach ($sizes as $key => $option)
            <form method="POST" action="{{ route('settings.size') }}" @if ($current !== $key) onsubmit="return confirm('Apply {{ $option['label'] }} foundry size? This changes the menu and replaces the process rules with the {{ ucfirst(strtolower($option['preset'])) }} preset.')" @endif>
                @csrf @method('PUT')
                <input type="hidden" name="size" value="{{ $key }}">
                <h2 style="margin-top:0">{{ $option['label'] }} @if ($current === $key)<span class="status">In use</span>@endif</h2>
                <p>{{ $option['description'] }}</p>
                <button class="btn {{ $current === $key ? '' : 'btn-primary' }}" type="submit">{{ $current === $key ? 'Re-apply' : 'Use '.$option['label'] }}</button>
            </form>
        @endforeach
    </div>
</div></div>
@endsection
