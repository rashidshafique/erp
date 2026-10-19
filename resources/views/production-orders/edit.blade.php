@extends('layouts.app')

@section('title', 'Edit '.$order->production_order_number)
@section('breadcrumb', 'Production orders / '.$order->production_order_number.' / Edit')

@section('content')
    <div class="page-heading">
        <div>
            <h1>Edit {{ $order->production_order_number }}</h1>
            <p>Only draft orders can be edited. Once released, the quantity and product are fixed.</p>
        </div>
        <a href="{{ route('production-orders.show', $order) }}" class="btn">Back to order</a>
    </div>

    <form method="POST" action="{{ route('production-orders.update', $order) }}">
        @csrf
        @method('PUT')
        @include('production-orders._form')
        <div class="actions" style="justify-content:flex-end;margin-bottom:25px">
            <a href="{{ route('production-orders.show', $order) }}" class="btn">Cancel</a>
            <button type="submit" class="btn btn-primary">Save changes</button>
        </div>
    </form>
@endsection
