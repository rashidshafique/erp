@extends('layouts.app')

@section('title', 'Materials')
@section('breadcrumb', 'Materials')

@section('content')
<div class="page-heading">
    <div>
        <h1>Materials</h1>
        <p>Charge inputs, consumables and stock materials. Open one for lots, receipts and inspection plan.</p>
    </div>
</div>

<livewire:material-list />
@endsection
