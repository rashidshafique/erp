@extends('layouts.app')

@section('title', 'Reorder alerts')
@section('breadcrumb', 'Reorder alerts')

@section('content')
<div class="page-heading">
    <div>
        <h1>Reorder alerts</h1>
        <p>Materials at their minimum. Store managers are told once each time one drops.</p>
    </div>
</div>

<livewire:inventory::reorder-alerts />
@endsection
