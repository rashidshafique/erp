@extends('m.layout')
@section('title', 'Today')
@section('body')
@php
    $roles = $user?->roles->pluck('name')->implode(' · ');
    $heatWhen = fn ($h) => ($h->actual_start_at ?? $h->planned_start_at ?? $h->created_at)?->format('j M H:i');
@endphp
<div style="padding:16px 20px 8px;display:flex;justify-content:space-between;align-items:flex-start;gap:10px">
    <div>
        <div class="muted" style="font-size:12.5px">{{ $roles ?: 'Shop floor' }}{{ $user ? ' · '.$user->name : '' }}</div>
        <div style="font-size:22px;font-weight:600">Today</div>
    </div>
    @if($user)<form method="POST" action="{{ route('logout') }}">@csrf<input type="hidden" name="mobile" value="1"><button class="chip" style="font:inherit;font-size:13px;cursor:pointer">Sign out</button></form>@endif
</div>

@if($tests)
    <div style="padding:8px 20px 0"><div class="cap" style="padding-bottom:6px">Record a test · {{ $todayTests }} thermal today</div>
        <div style="display:flex;flex-direction:column;gap:10px">
            @foreach($types as $key => $t)
                <a class="tile" href="{{ route('m.tests.pick', $key) }}"><b>{{ $t['name'] }}</b><span>{{ $t['sub'] }}</span></a>
            @endforeach
        </div>
    </div>
    <div style="padding:16px 20px 0"><div class="cap" style="padding-bottom:2px">Latest heats</div>
        @forelse($recentHeats as $h)
            <a class="row" href="{{ route('m.tests.pick', 'thermal') }}"><span><b>{{ $h->furnace?->code ?? $h->furnace?->name }} · <code>{{ $h->heat_number }}</code></b><br><span class="muted" style="font-size:13px">{{ $h->ironGrade?->code }} · {{ $heatWhen($h) }}</span></span><span class="muted" style="font-size:12.5px">{{ str_replace('_', ' ', strtolower($h->status)) }}</span></a>
        @empty
            <div class="muted" style="font-size:14px;padding:10px 0">No heats yet.</div>
        @endforelse
    </div>
@endif

@if($defects)
    <div style="padding:16px 20px 0"><div class="cap" style="padding-bottom:6px">Defects · {{ $todayDefects }} logged today</div>
        <a class="tile" href="{{ route('m.defects.pick') }}"><b>Log defect</b><span>Pick the lot, then photos + type</span></a>
    </div>
@endif
@endsection
