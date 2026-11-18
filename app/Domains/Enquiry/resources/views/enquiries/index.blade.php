{{-- The enquiries list (design 14a); the list itself is the enquiry-list Livewire component. --}}
@extends('layouts.app')
@section('title', 'Enquiries')
@section('breadcrumb', 'Enquiries')
@section('content')
@include('enquiry::partials._list_styles')
@include('partials.list-ui')
<livewire:enquiry::enquiry-list />
@endsection
