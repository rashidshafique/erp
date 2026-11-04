@extends('layouts.app')

@section('title', 'Record thermal test')
@section('breadcrumb', 'Thermal tests / New')

@php
    $nowLocal = now()->format('Y-m-d\TH:i');
@endphp

@section('content')
<div class="page-heading"><div><h1>Record thermal test</h1><p>Key in the white and/or grey cup readings. Add the cooling curve when you have it.</p></div></div>

<form method="POST" action="{{ route('thermal.store') }}" enctype="multipart/form-data" data-offline data-offline-label="Thermal analysis test" data-offline-done="{{ route('m.home') }}">@csrf
@include('thermal._fields', ['ip' => '', 'mine' => true])
@include('thermal._fields-assets')
<button class="btn btn-primary" type="submit">Analyse and save</button>
</form>

@endsection
