@extends('layouts.app')

@section('content')
<div class="page-header">
    <div><h1>Edit {{ $chargeBatch->name }}</h1><p>{{ $chargeBatch->code }} · Revision {{ $chargeBatch->revision }}. Heats already melted keep what they recorded; their planned coke and flux follow the recipe as saved.</p></div>
</div>

@include('charge-batches._form')
@endsection
