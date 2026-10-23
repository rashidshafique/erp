@extends('layouts.app')

@section('title', 'Edit '.$material->code)
@section('breadcrumb')<a href="{{ route('materials.index') }}">Materials</a> / <a href="{{ route('materials.show', $material) }}">{{ $material->code }}</a> / Edit @endsection

@section('content')
<div class="page-heading">
    <div><h1>Edit material</h1><p>{{ $material->code }} — {{ $material->name }}</p></div>
</div>

<form method="POST" action="{{ route('materials.update', $material) }}">
    @method('PUT')
    @include('materials._form')
</form>
@endsection
