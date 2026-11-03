@extends('layouts.app')

@section('title', 'Modules')
@section('breadcrumb', 'Settings / Modules')

@section('content')
<div class="page-heading">
    <div><h1>Modules</h1><p>Switch off what this foundry does not use. Data is kept and returns when switched back on.</p></div>
</div>
<form method="POST" action="{{ route('settings.modules') }}">
    @csrf @method('PUT')
    <div class="panel"><div class="panel-body">
        <div class="rule-grid">
            @foreach ($modules as $name => $module)
                <fieldset>
                    <legend>{{ $module['label'] }}</legend>
                    <label><input class="switch" type="checkbox" name="modules[]" value="{{ $name }}" @checked($module['enabled']) @disabled(! $module['licensed'])> On</label>
                    @if (! $module['licensed'])<p class="muted small" style="margin:8px 0 0">Not licensed for this installation.</p>@endif
                    @if ($module['description'])<p class="muted small" style="margin:8px 0 0">{{ $module['description'] }}</p>@endif
                </fieldset>
            @endforeach
        </div>
        <p class="muted small" style="margin:16px 0 0">An off module's menu entries, pages and links are hidden.</p>
    </div></div>
    <div class="actions"><button class="btn btn-primary" type="submit">Save modules</button></div>
</form>
@endsection
