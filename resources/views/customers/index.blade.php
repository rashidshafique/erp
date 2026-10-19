@extends('layouts.app')

@section('title', 'Customers')
@section('breadcrumb', 'Customers')

@section('content')
<div class="page-heading">
    <div><h1>Customers</h1><p>Customer accounts for sales orders and dispatch.</p></div>
    <div class="actions"><a class="btn btn-primary" href="{{ route('customers.create') }}">+ New customer</a></div>
</div>

@if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

<div class="panel"><div class="panel-body">
    <form method="GET" class="filters">
        <div class="field"><label for="search">Search code or name</label><input id="search" name="search" value="{{ request('search') }}"></div>
        <button class="btn btn-primary" type="submit">Filter</button>
        <a class="btn" href="{{ route('customers.index') }}">Reset</a>
    </form>
</div></div>

<div class="panel">
    @if ($customers->isEmpty())
        <div class="empty-state">No customers yet.</div>
    @else
        <div class="table-wrap"><table>
            <thead><tr><th>Code</th><th>Name</th><th>Contact</th><th>Payment terms</th><th>Orders</th><th>Patterns held</th><th>Status</th></tr></thead>
            <tbody>
            @foreach ($customers as $c)
                <tr>
                    <td><strong>{{ $c->code }}</strong></td><td>{{ $c->name }}</td>
                    <td>{{ $c->contact_name ?: '—' }}{{ $c->email ? ' · '.$c->email : '' }}</td>
                    <td>{{ $c->payment_terms ?: '—' }}</td>
                    <td><a href="{{ route('sales-orders.index', ['search' => '']) }}">{{ $c->sales_orders_count }}</a></td>
                    <td>@if ($c->owned_patterns_count)<a href="{{ route('patterns.index', ['owner' => $c->id]) }}">{{ $c->owned_patterns_count }}</a>@else — @endif</td>
                    <td>{{ $c->active ? 'Active' : 'Inactive' }} · <a href="{{ route('customer-requirements.edit', $c) }}">Requirements</a></td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        <div class="panel-body">{{ $customers->links() }}</div>
    @endif
</div>
@endsection
