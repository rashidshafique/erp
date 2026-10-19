@extends('layouts.app')

@section('title', 'New production order')
@section('breadcrumb', 'Production orders / New order')

@section('content')
    <div class="page-heading">
        <div>
            <h1>New production order</h1>
            <p>Create a draft first. Release it only when it is ready for the shop floor. The order number is assigned when you save.</p>
        </div>
        <a href="{{ route('production-orders.index') }}" class="btn">Back to orders</a>
    </div>

    <form method="POST" action="{{ route('production-orders.store') }}">
        @csrf
        @include('production-orders._form', ['order' => null])
        <div class="actions" style="justify-content:flex-end;margin-bottom:25px">
            <a href="{{ route('production-orders.index') }}" class="btn">Cancel</a>
            <button type="submit" class="btn btn-primary">Save draft order</button>
        </div>
    </form>
@endsection
