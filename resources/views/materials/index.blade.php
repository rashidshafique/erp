@extends('layouts.app')

@section('title', 'Materials')
@section('breadcrumb', 'Materials')

@section('content')
<div class="page-heading">
    <div>
        <h1>Materials</h1>
        <p>Charge inputs, consumables and traceable stock materials. Add one with the button; open it for lots, receipts and the inspection plan.</p>
    </div>
</div>

<livewire:material-list />
@endsection
