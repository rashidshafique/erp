@extends('layouts.app')

{{-- A page of foundry-wide rules: each rule is a set of choices, or a number. Used for the flask and pattern ownership rules. --}}
@section('title', $title)
@section('breadcrumb', 'Settings / '.$title)

@section('content')
<div class="page-heading">
    <div><h1>{{ $title }}</h1><p>{{ $intro }}</p></div>
    <div class="actions"><a class="btn" href="{{ $backUrl }}">{{ $backLabel }}</a></div>
</div>
<form method="POST" action="{{ $action }}">
    @csrf @method('PUT')
    <div class="panel"><div class="panel-body">
        <div class="rule-grid">
            @foreach ($rules as $key => [, $label, $options])
                <fieldset>
                    <legend>{{ $label }}</legend>
                    @if ($options === null)
                        <input type="number" name="{{ $key }}" value="{{ old($key, $values[$key]) }}" min="0" step="1" style="max-width:140px" aria-label="{{ $label }}">
                    @else
                        @foreach ($options as $value => $text)<label><input type="radio" name="{{ $key }}" value="{{ $value }}" @checked(old($key, $values[$key]) === $value)> {{ $text }}</label>@endforeach
                    @endif
                </fieldset>
            @endforeach
        </div>
        @if ($note)<p class="muted small" style="margin:16px 0 0">{{ $note }}</p>@endif
    </div></div>
    <div class="actions"><button class="btn btn-primary" type="submit">Save rules</button></div>
</form>
@endsection
