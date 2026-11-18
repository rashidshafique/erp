{{-- The employees list; the list itself is the employee-list Livewire component. --}}
@extends('layouts.app')
@section('title', 'Employees')
@section('breadcrumb', 'Employees')
@section('content')
@include('hr::_list_styles')
@include('partials.list-ui')
<livewire:hr::employee-list />
@endsection
