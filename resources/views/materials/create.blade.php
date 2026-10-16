@extends('layouts.app')

@section('content')
<div class="page-header">
    <div><h1>New material</h1><p>Create a reusable material master record.</p></div>
</div>

<form method="POST" action="{{ route('materials.store') }}">
    @include('materials._form')
</form>
@endsection
