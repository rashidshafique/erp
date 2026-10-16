@extends('layouts.app')
@section('title', 'Not allowed')
@section('content')
<div class="page-heading"><div><h1>Not allowed</h1><p>{{ $why ?? 'You do not have access to this.' }}</p></div></div>
<div class="panel"><div class="panel-body">Ask an administrator to change your role if you need this. <a href="{{ route('dashboard') }}">Back to dashboard</a></div></div>
@endsection
