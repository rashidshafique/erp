{{-- The employees list; the list itself is the employee-list Livewire component. --}}
@extends('layouts.app')
@section('title', 'Employees')
@section('breadcrumb', 'Employees')
@section('content')
@include('partials.record-ui')
@include('partials.list-ui')
<livewire:hr::employee-list />
@endsection
