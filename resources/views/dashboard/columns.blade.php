@extends('layouts.app')
@section('title', 'Dashboard')
@section('breadcrumb', 'Dashboard')
@section('content')
<div class="dx stack">
    @include('dashboard._style')
    @include('dashboard._approvals')
    @include('dashboard._hero', ['strip' => true])
    <div class="g-columns">
        <div>
            @include('dashboard._attention')
            @include('dashboard._sand')
            @include('dashboard._scan')
        </div>
        <div>
            @include('dashboard._chart')
            @include('dashboard._floor')
            @include('dashboard._queue')
        </div>
        <div>
            @include('dashboard._kpis')
        </div>
    </div>
</div>
@endsection
