@extends('layouts.app')

@section('title', 'Our process')
@section('breadcrumb', 'Settings / Our process')

@section('content')
<div class="page-heading">
    <div><h1>Our process</h1><p>The steps every casting goes through after the pour, in the order your foundry does them. The production queue, the board and the move form follow this list.</p></div>
    <div class="actions"><a class="btn" href="{{ route('settings.control-plans.edit') }}">Control plan rules</a><a class="btn" href="{{ route('lots.queue') }}">Production queue</a></div>
</div>

<livewire:process-route />

@php $plans = app(\App\Services\Production\ControlPlans::class); @endphp
<div class="panel">
    <div class="panel-header"><h2>Default control plan</h2><span class="muted small">What is controlled at each step. Every product revision follows this until it is given its own plan.</span></div>
    <div class="panel-body">@include('control-plans._rows', ['items' => $plans->defaultPlan()->items, 'action' => route('settings.process.control-plan')])</div>
</div>
@endsection
