@extends('layouts.app')

@section('content')
<div class="page-header">
    <div><h1>Edit {{ $chargeBatch->name }}</h1><p>{{ $chargeBatch->code }} · Revision {{ $chargeBatch->revision }}. Melted heats keep what they recorded.</p></div>
</div>

@include('charge-batches._form')
@endsection
