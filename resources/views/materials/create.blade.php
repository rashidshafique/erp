@extends('layouts.app')

@section('title', 'New material')
@section('breadcrumb')<a href="{{ route('materials.index') }}">Materials</a> / New @endsection

@section('content')
<div class="page-heading">
    <div><h1>New material</h1><p>Create a reusable material master record.</p></div>
</div>

<form method="POST" action="{{ route('materials.store') }}">
    @include('materials._form')
</form>
@endsection
