@extends('layouts.app')

@section('title', 'Suppliers')
@section('breadcrumb', 'Suppliers')

@section('content')
<div class="page-heading">
    <div>
        <h1>Suppliers</h1>
        <p>The approved supplier list. Material lots can name the supplier they came from; suspended suppliers cannot be received from.</p>
    </div>
</div>

<livewire:purchasing::supplier-list />
@endsection
