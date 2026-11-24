@extends('layouts.app')

@section('title', 'New sales order')
@section('breadcrumb', 'Sales orders / New')

@section('content')
@include('sales::orders._order-form', $form + ['order' => null, 'copyFrom' => null, 'fromQuote' => $q, 'action' => route('quotations.order.store', $q), 'title' => 'New sales order', 'submit' => 'Create order', 'cancel' => route('quotations.show', $q), 'needsReason' => false, 'started' => false])
@endsection
