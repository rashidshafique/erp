@extends('layouts.app')

@section('title', 'Stock by location')
@section('breadcrumb', 'Stock by location')

@section('content')
<div class="page-heading">
    <div>
        <h1>Stock by location</h1>
        <p>Where each lot's stock is kept. Stock received without a location shows as not put anywhere.</p>
    </div>
    <div class="actions"><a class="btn btn-primary" href="{{ route('stock-movements.record') }}">+ Record movement</a></div>
</div>

<livewire:inventory::stock-by-location />
@endsection
