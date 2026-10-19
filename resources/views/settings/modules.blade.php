@extends('layouts.app')

@section('title', 'Modules')
@section('breadcrumb', 'Settings / Modules')

@section('content')
<div class="page-heading">
    <div><h1>Modules</h1><p>Switch off the parts of the system this foundry does not use. Nothing is deleted: the data stays and comes back when the module is switched on again.</p></div>
</div>
<form method="POST" action="{{ route('settings.modules') }}">
    @csrf @method('PUT')
    <div class="panel"><div class="panel-body">
        <div class="rule-grid">
            @foreach ($modules as $name => $module)
                <fieldset>
                    <legend>{{ $module['label'] }}</legend>
                    <label><input type="checkbox" name="modules[]" value="{{ $name }}" @checked(! in_array($name, $disabled, true))> On</label>
                    @if ($module['description'])<p class="muted small" style="margin:8px 0 0">{{ $module['description'] }}</p>@endif
                </fieldset>
            @endforeach
        </div>
        <p class="muted small" style="margin:16px 0 0">Switching a module off hides its menu entries and pages. Other screens that link to it drop the link.</p>
    </div></div>
    <div class="actions"><button class="btn btn-primary" type="submit">Save modules</button></div>
</form>
@endsection
