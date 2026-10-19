@extends('layouts.app')
@section('title', 'New employee')
@section('breadcrumb')<a href="{{ route('hr.employees.index') }}">Employees</a> / New @endsection
@section('content')
<div class="page-heading"><div><h1>New employee</h1><p>Add the person and where they sit. The joining checklist is created for you.</p></div>
<div class="actions"><a class="btn" href="{{ route('hr.employees.index') }}">Cancel</a></div></div>
<form method="POST" action="{{ route('hr.employees.store') }}">@csrf
    @include('hr::employees._fields', ['e' => null])
    <p><button class="btn btn-primary" type="submit">Add employee</button> <a class="btn" href="{{ route('hr.employees.index') }}">Cancel</a></p>
</form>
@endsection
