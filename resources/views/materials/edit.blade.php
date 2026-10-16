@extends('layouts.app')

@section('content')
<div class="page-header">
    <div><h1>Edit material</h1><p>{{ $material->code }} — {{ $material->name }}</p></div>
</div>

<form method="POST" action="{{ route('materials.update', $material) }}">
    @method('PUT')
    @include('materials._form')
</form>
@endsection
