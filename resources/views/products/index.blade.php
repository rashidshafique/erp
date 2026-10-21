
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

<div class="metrics">
    <div class="metric">
        <div class="metric-label">Total products</div>
        <div class="metric-value">{{ \App\Support\Formats::number($totalProducts) }}</div>
    </div>
    <div class="metric">
        <div class="metric-label">Active</div>
        <div class="metric-value">{{ \App\Support\Formats::number($activeProducts) }}</div>
    </div>
    <div class="metric">
        <div class="metric-label">Inactive</div>
        <div class="metric-value">{{ \App\Support\Formats::number($inactiveProducts) }}</div>
    </div>
    <div class="metric">
        <div class="metric-label">Matching results</div>
        <div class="metric-value">{{ \App\Support\Formats::number($products->total()) }}</div>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <form method="GET" action="{{ route('products.index') }}" class="filters">
            <div class="field">
                <label for="search">Search code or name</label>
                <input id="search" name="search" value="{{ request('search') }}"
                       placeholder="e.g. VALVE-001">
            </div>
            <div class="field">
                <label for="active">Product status</label>
                <select id="active" name="active">
                    <option value="">All products</option>
                    <option value="1" @selected(request('active') === '1')>Active</option>
                    <option value="0" @selected(request('active') === '0')>Inactive</option>
                </select>
            </div>
            <button class="btn btn-primary" type="submit">Filter</button>
            <a class="btn" href="{{ route('products.index') }}">Reset</a>
        </form>
    </div>
</div>

<div class="panel">
    <div class="panel-header">
        <h2>{{ $archived ? 'Archived products' : 'Products' }}</h2>
        <span class="muted small">{{ $products->total() }} records ·
            @if ($archived)<a href="{{ route('products.index') }}">Back to current products</a>@elseif ($archivedProducts)<a href="{{ route('products.index', ['archived' => 1]) }}">{{ $archivedProducts }} archived</a>@else none archived @endif</span>
    </div>

    @if ($products->isEmpty())
        <div class="empty-state">
            <strong>{{ $archived ? 'No archived products' : 'No products found' }}</strong>
            <p>Create your first product to start building the production workflow.</p>
            <a class="btn btn-primary" href="{{ route('products.create') }}">Create product</a>
        </div>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Type</th>
                        <th>Nominal weight</th>
                        <th>Revisions</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($products as $product)
                        <tr>
                            <td><x-product-cell :product="$product" /></td>
                            <td>{{ $product->product_type }}</td>
                            <td>
                                {{ $product->nominal_weight_kg !== null
                                    ? \App\Support\Formats::number((float) $product->nominal_weight_kg, 4).' kg'
                                    : '—' }}
                            </td>
                            <td>{{ $product->revisions_count }}</td>
                            <td>
                                <span class="status {{ $product->active ? 'status-completed' : 'status-cancelled' }}">
                                    {{ $product->active ? 'ACTIVE' : 'INACTIVE' }}
                                </span>
                            </td>
                            <td style="white-space:nowrap"><a href="{{ route('products.show', $product) }}">Open →</a>@if ($archived)<form method="POST" action="{{ route('products.restore', $product) }}" style="display:inline;margin-left:8px">@csrf<button class="btn btn-small" type="submit">Restore</button></form>@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="pagination">
            <div class="muted small">
                Showing {{ $products->firstItem() }}–{{ $products->lastItem() }}
                of {{ $products->total() }}
            </div>
            <div class="pagination-links">{{ $products->links() }}</div>
        </div>
    @endif
</div>
@endsection
