@extends('layouts.app')

@section('title', 'Our process')
@section('breadcrumb', 'Settings / Our process')

@section('content')
<div class="page-heading">
    <div><h1>Our process</h1><p>Steps after the pour, in your order. The queue, board and move form follow it.</p></div>
    <div class="actions"><a class="btn" href="{{ route('settings.rules').'#quality' }}">Control plan rules</a><a class="btn" href="{{ route('lots.queue') }}">Production queue</a></div>
</div>

<livewire:process-route />

@php $plans = app(\App\Services\Production\ControlPlans::class); @endphp
<div class="panel">
    <div class="panel-header"><h2>Default control plan</h2><span class="muted small">Applies to every product revision without its own plan.</span></div>
    <div class="panel-body">@include('control-plans._steps', ['items' => $plans->defaultPlan()->items()->with('stageDefinition')->get(), 'stages' => \App\Models\ProductionStageDefinition::where('is_active', true)->orderBy('sequence')->get(), 'action' => route('settings.process.control-plan')])</div>
</div>
@endsection
