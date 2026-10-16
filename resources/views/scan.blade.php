@extends('layouts.app')
@section('title', 'Scan or find')
@section('content')
<div class="page-heading"><div><h1>Scan or find</h1><p>Scan a barcode or type a code: a product, pattern, tooling code or engraved mark, a lot, heat, pour or order number.</p></div></div>
<div class="panel"><div class="panel-body">
<form method="GET" action="{{ route('scan') }}" style="display:flex;gap:8px;max-width:520px"><input name="code" value="{{ $code ?? '' }}" placeholder="Scan or type a code" autofocus autocomplete="off"><button class="btn btn-primary">Find</button></form>
@isset($missing)<p class="error-text">Nothing has the code “{{ $code }}”.</p>@endisset
</div></div>
@endsection
