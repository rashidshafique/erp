@extends('layouts.app')

@section('content')
<div class="page-header">
    <div><h1>New charge recipe</h1><p>Choose one calculation method: fixed quantities or percentages.</p></div>
</div>


@if($materials->isEmpty())
    <div class="alert alert-warning">
        Create active materials before defining a recipe.
        <a href="{{ route('materials.create') }}">Create a material</a>
    </div>
@endif

@include('charge-batches._form')
@endsection
