{{-- Maintenance (design 7k): one screen with Work orders, Plans, Breakdowns and Assets, four figures across the top, a table
     in the main column and the meters and spares used in a 340px rail. --}}
@extends('layouts.app')

@section('title', 'Maintenance')
@section('breadcrumb', 'Maintenance')

@section('content')
@php
    $fmt = fn ($value, int $decimals = 0): string => \App\Support\Formats::number($value, $decimals);
    $hours = fn ($value): string => rtrim(rtrim(\App\Support\Formats::number($value, 2), '0'), '.');
    $tabUrl = fn (string $key): string => route('equipment-maintenance.index', $key === 'work-orders' ? [] : ['tab' => $key]);
    $pill = fn (string $state): string => match ($state) {
        'in_progress', 'completed' => 'el-blue',
        'on_hold', 'due', 'due_soon' => 'el-warn',
        'overdue', 'meter_due', 'cancelled' => 'el-bad',
        default => '',
    };
    $statusTone = fn (string $status): string => match ($status) {
        'BREAKDOWN', 'RETIRED', 'DISPOSED' => 'el-bad',
        'MAINTENANCE' => 'el-warn',
        'IN_USE' => 'el-blue',
        default => '',
    };
    $nextMeter = $nextMeterPlan;
    $nextMeterMeter = $nextMeter?->meter();
    $nextMeterRemaining = $nextMeter !== null && $nextMeterMeter !== null
        ? (float) $nextMeter->next_due_meter - (float) $nextMeterMeter->current_reading
        : null;
@endphp

@include('maintenance::equipment._styles')

@include('partials.list-ui')
@php $details = \App\Support\ListLayout::details(); @endphp
<div class="el-card ls-card">
    <div class="ls-head">
        <h1 class="ls-title">Maintenance</h1>
        <div class="ls-actions">
            <button type="button" class="ls-btn" data-quick="qd-breakdown">Log breakdown</button>
            <button type="button" class="ls-btn primary" data-quick="qd-work-order">New work order</button>
        </div>
    </div>
    <nav class="ls-tabs" aria-label="Maintenance">
        @foreach ($tabs as $key => $label)<a href="{{ $tabUrl($key) }}" @class(['on' => $tab === $key])>{{ $label }}</a>@endforeach
        <span style="margin-left:auto;align-self:center">@include('partials.list-details-form')</span>
    </nav>

    <div class="el-stats">
        <div class="el-stat">
            <div class="el-stat-label">Open work orders</div>
            <div class="el-stat-value">{{ $openWorkOrders->count() }}</div>
            <div class="el-stat-note @if ($overdueCount > 0) bad @endif">{{ $overdueCount }} overdue</div>
        </div>
        <div class="el-stat">
            <div class="el-stat-label">Downtime, {{ $monthName }}</div>
            <div class="el-stat-value">{{ $hours($downtimeHours) }} h</div>
            <div class="el-stat-note">@if ($downtimeTop){{ $downtimeTop['code'] }} · {{ $hours($downtimeTop['hours']) }} h @else No breakdowns @endif</div>
        </div>
        <div class="el-stat">
            <div class="el-stat-label">Plan compliance</div>
            <div class="el-stat-value">{{ $planCompliance !== null ? $planCompliance.'%' : '—' }}</div>
            <div class="el-stat-note">target {{ $complianceTarget }}%</div>
        </div>
        <div class="el-stat">
            <div class="el-stat-label">Next meter service</div>
            <div class="el-stat-value">{{ $nextMeter?->equipment?->code ?? '—' }}</div>
            <div class="el-stat-note">
                @if ($nextMeter !== null && $nextMeterRemaining !== null)
                    {{ $nextMeterRemaining <= 0 ? 'due now' : 'in '.$fmt($nextMeterRemaining).' '.($nextMeterMeter?->shortUnit() ?? 'units') }}
                @else
                    No meter plans
                @endif
            </div>
        </div>
    </div>

    <div class="el-grid" @style(['grid-template-columns: minmax(0, 1fr)' => ! $details])>
        <div class="el-main">
            @switch($tab)
                @case('plans')
                    @include('maintenance::equipment._plans')
                    @break
                @case('breakdowns')
                    @include('maintenance::equipment._breakdowns')
                    @break
                @case('assets')
                    @include('maintenance::equipment._assets')
                    @break
                @default
                    @include('maintenance::equipment._work-orders')
            @endswitch
        </div>
        @if ($details)<aside class="el-side"><div class="ls-ph" style="justify-content:flex-end">@include('partials.list-hide-form')</div>@include('maintenance::equipment._rail')</aside>@endif
    </div>
</div>

@include('maintenance::equipment._dialogs')
@endsection
