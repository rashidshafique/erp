@extends('layouts.app')

@section('title', 'Stock locations')
@section('breadcrumb', 'Stock locations')

@section('content')
<div class="page-heading">
    <div>
        <h1>Stock locations</h1>
        <p>Stores, yards and bins. A location in use can be switched off, not deleted.</p>
    </div>
</div>

<livewire:inventory::location-list />
@endsection
