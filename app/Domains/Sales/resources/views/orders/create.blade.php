@extends('layouts.app')

@section('title', 'New sales order')
@section('breadcrumb', 'Sales orders / New')

@section('content')
@include('sales::orders._order-form', ['order' => null, 'action' => route('sales-orders.store'), 'title' => 'New sales order', 'submit' => 'Create order', 'cancel' => route('sales-orders.index'), 'needsReason' => false, 'started' => false])
@endsection
