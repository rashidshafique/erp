@extends('layouts.app')
@section('title', 'Chart of accounts')
@section('breadcrumb', 'Chart of accounts')
@section('content')
<livewire:ledger::chart />
@endsection
