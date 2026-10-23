@extends('layouts.app')

@section('title', 'Products')
@section('breadcrumb', 'Products')

@section('content')
<div class="page-heading">
    <div>
        <h1>Product master</h1>
        <p>Manage casting codes, product specifications and revisions.</p>
    </div>
    <div class="actions">
        <a class="btn" href="{{ route('imports.create', ['entity' => 'product']) }}">Import</a> <a class="btn btn-primary" href="{{ route('products.create') }}">+ New product</a>
    </div>
</div>

<livewire:product-list />
@endsection
