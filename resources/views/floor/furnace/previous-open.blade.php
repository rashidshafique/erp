@extends('layouts.app')

@section('title', 'A heat is still open')
@section('breadcrumb', 'Furnace / Start heat')

{{-- design: F4·W warns when another furnace still has a heat open; it never blocks --}}
@section('content')
<div class="fl-gloves fl-has-tabs">
    @include('floor.furnace._frame', ['active' => 'heat'])

    <h1 style="margin:0;font-size:24px">Start a heat on {{ $next['furnace']->code }}?</h1>
    <x-floor.blocker state="warn" :title="$other->heat_number.' on '.($other->furnace?->code ?? '—').' is still open'"
        :detail="'Open since '.($other->actual_start_at ?? $other->created_at)->diffForHumans(null, true).'. Its metal balance and yield stay open until it is closed. '.($other->furnace_id === $next['furnace']->id ? '' : $other->furnace?->code.' and '.$next['furnace']->code.' are different furnaces, so this warns but does not block.')" />
    <div style="display:grid;gap:12px;margin-top:16px">
        <x-floor.big-button tone="warn" :label="'Close '.$other->heat_number.' first'" sub="then back here to start" :href="route('floor.furnace.heat.close', $other)" />
        <a class="fl-btn" href="{{ route('floor.furnace.heat.start.create', ['heat' => $index, 'anyway' => 1]) }}">Start anyway</a>
    </div>
</div>
@endsection
