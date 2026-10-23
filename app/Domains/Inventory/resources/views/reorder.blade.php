@extends('layouts.app')

@section('title', 'Reorder alerts')
@section('breadcrumb', 'Reorder alerts')

@section('content')
<div class="page-heading">
    <div>
        <h1>Reorder alerts</h1>
        <p>Materials that have fallen to their minimum stock. Everyone who manages stores is told once each time a material drops to its minimum.</p>
    </div>
</div>

<livewire:inventory::reorder-alerts />
@endsection
