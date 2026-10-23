@extends('layouts.app')

@section('title', 'Our process')
@section('breadcrumb', 'Settings / Our process')

@section('content')
<div class="page-heading">
    <div><h1>Our process</h1><p>The steps every casting goes through after the pour, in the order your foundry does them. The production queue, the board and the move form follow this list.</p></div>
    <div class="actions"><a class="btn" href="{{ route('lots.queue') }}">Production queue</a></div>
</div>

<livewire:process-route />
@endsection
