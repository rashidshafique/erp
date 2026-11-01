@extends('layouts.app')
@section('title', $voucher?->number ?? 'New voucher')
@section('breadcrumb', 'Journal')
@section('content')
<livewire:ledger::voucher-form :voucher="$voucher" :type="$type" />
@endsection
