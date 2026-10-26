@extends('layouts.app')

@section('title', 'Edit '.$order->order_number)
@section('breadcrumb', 'Sales orders / '.$order->order_number.' / Edit')

@section('content')
@include('sales::orders._order-form', [
    'action' => route('sales-orders.update', $order), 'title' => 'Edit '.$order->order_number, 'submit' => 'Save changes', 'cancel' => route('sales-orders.show', $order),
    'started' => $order->lines->contains(fn ($l) => $l->productionOrders->where('status', '!=', 'CANCELLED')->isNotEmpty()),
])
@endsection
