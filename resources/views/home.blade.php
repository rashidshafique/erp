@extends('layouts.app')
@section('title', 'Home')
@section('breadcrumb', 'Home')
@section('content')
@php $first = strtok($user?->name ?? '', ' '); @endphp
<style>
.rh { display: flex; flex-direction: column; gap: 20px; }
.rh h1 { margin: 0; font-size: 22px; font-weight: 600; letter-spacing: -.01em; }
.rh h2 { margin: 0 0 8px; font-size: 13px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .04em; }
.rh-tiles { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 12px; }
.rh-tile { display: block; border: 1px solid var(--border); border-radius: 8px; padding: 14px 16px; background: var(--surface); color: inherit; text-decoration: none; }
.rh-tile .l { font-size: 12px; color: var(--muted); } .rh-tile .v { margin-top: 4px; font-size: 24px; font-weight: 600; }
.rh-rows { border: 1px solid var(--border); border-radius: 8px; background: var(--surface); }
.rh-row { display: flex; align-items: center; gap: 12px; padding: 10px 14px; border-top: 1px solid var(--border); } .rh-row:first-child { border-top: 0; }
.rh-row .t { flex: 1; min-width: 0; } .rh-row .t b { font-weight: 600; } .rh-row .d { font-size: 12px; color: var(--muted); } .rh-row.bad .t b { color: #b3352c; }
.rh-empty { padding: 12px 14px; font-size: 13px; color: var(--muted); }
.rh-tile.warn .v { color: #94610a; } .rh-tile.bad .v { color: #b3352c; } .rh-tile.link .v { font-size: 15px; color: var(--accent); }
</style>
<div class="rh">
    <div><h1>{{ $first ? 'Hello, '.$first : 'Home' }}</h1><div class="muted small">{{ $user?->roles->pluck('name')->implode(' · ') }} · {{ now()->format('l j F') }}</div></div>
    @foreach($sections as $s)
        <div><h2>{{ $s['title'] }}@if(!empty($s['rows'])) · {{ count($s['rows']) }}@endif</h2>
        @if(array_key_exists('rows', $s))
            <div class="rh-rows">
            @forelse($s['rows'] as $r)
                <div class="rh-row {{ $r['tone'] }}"><div class="t"><b>{{ $r['title'] }}</b><div class="d">{{ $r['detail'] }}</div></div>
                    @if($r['post'] !== null)<form method="POST" action="{{ $r['url'] }}">@csrf @foreach($r['post'] as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach<button class="btn btn-primary">{{ $r['action'] }}</button></form>
                    @else<a class="btn btn-primary" href="{{ $r['url'] }}">{{ $r['action'] }}</a>@endif</div>
            @empty<div class="rh-empty">{{ $s['empty'] }}</div>@endforelse
            </div>
        @else<div class="rh-tiles">
            @foreach($s['tiles'] as $t)<a class="rh-tile {{ $t['tone'] }}" href="{{ $t['url'] }}"><div class="l">{{ $t['label'] }}</div><div class="v">{{ $t['tone'] === 'link' ? 'Open' : $t['value'] }}</div></a>@endforeach
        </div>@endif</div>
    @endforeach
</div>
@endsection
