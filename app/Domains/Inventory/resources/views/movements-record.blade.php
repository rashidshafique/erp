@extends('layouts.app')

@section('title', 'Record stock movement')
@section('breadcrumb', 'Record stock movement')

@section('content')
<div class="page-heading">
    <div>
        <h1>Record a stock movement</h1>
        <p>Issue, scrap, adjust after a count, or transfer stock between locations. Receipts are posted from the lot and charges from the heat.</p>
    </div>
</div>

<livewire:inventory::record-movement />
@endsection
