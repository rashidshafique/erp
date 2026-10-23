@extends('layouts.app')

@section('title', 'Stock locations')
@section('breadcrumb', 'Stock locations')

@section('content')
<div class="page-heading">
    <div>
        <h1>Stock locations</h1>
        <p>The stores, yards and bins where materials are kept. A location in use can be switched off but not deleted.</p>
    </div>
</div>

<livewire:inventory::location-list />
@endsection
