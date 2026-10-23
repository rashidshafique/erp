@extends('layouts.app')

@section('title', 'Customers')
@section('breadcrumb', 'Customers')

@section('content')
<div class="page-heading">
    <div><h1>Customers</h1><p>Customer accounts for sales orders and dispatch. Add one with the button; quick edit a row to change its details or mark it inactive.</p></div>
</div>

<livewire:customer-list />
@endsection
