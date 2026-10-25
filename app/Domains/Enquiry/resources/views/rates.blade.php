@extends('layouts.app')
@section('title', 'Quotation rates')
@section('content')
<div class="page-heading"><div><h1>Quotation rates</h1><p>The costs behind every quotation, in {{ $currency }}.</p></div></div>
<div class="alert alert-error">The figures shown first time are placeholders to show the screen working. Replace every one with your own costs before issuing a real quotation.</div>
<form method="POST" action="{{ route('settings.quotation.update') }}">@csrf @method('PUT')
<div class="panel"><div class="panel-body"><div class="form-grid">
@foreach ($labels as $k => [$label, $help])
<div class="field"><label for="r_{{ $k }}">{{ $label }}</label><input id="r_{{ $k }}" type="number" step="any" min="0" name="{{ $k }}" value="{{ old($k, $rates[$k]) }}" required>@if ($help)<div class="help">{{ $help }}</div>@endif</div>
@endforeach
</div></div></div>
<p><button class="btn btn-primary" type="submit">Save rates</button></p></form>
@endsection
