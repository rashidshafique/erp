@extends('m.layout')
@section('title', $title)
@section('body')
@php
    $q = fn (array $over) => route($route, $params + array_filter(array_merge(['on' => $either ? $on : null, 'size' => $size, 'within' => $within], $over), fn ($v) => $v !== null));
@endphp
<div style="padding:14px 20px 6px"><a href="{{ route('m.home') }}" class="blue" style="font-size:14px;text-decoration:none">‹ Today</a>
    <div style="font-size:22px;font-weight:600;margin-top:4px">{{ $title }}</div>
    <div class="muted" style="font-size:13px">Which {{ $on }}? Newest first.</div></div>

<div style="padding:4px 20px;display:flex;flex-direction:column;gap:8px">
    @if($either)<div style="display:flex;gap:6px">@foreach(['heat' => 'Heats', 'lot' => 'Lots'] as $k => $l)<a class="chip {{ $on === $k ? 'on' : '' }}" href="{{ $q(['on' => $k]) }}">{{ $l }}</a>@endforeach</div>@endif
    <div style="display:flex;gap:6px;flex-wrap:wrap">@foreach($windows as $k => $l)<a class="chip {{ (string) $within === (string) $k ? 'on' : '' }}" href="{{ $q(['within' => $k]) }}">{{ $l }}</a>@endforeach</div>
    <div style="display:flex;gap:6px;align-items:center"><span class="muted" style="font-size:13px">Show</span>@foreach($sizes as $n)<a class="chip {{ $size === $n ? 'on' : '' }}" href="{{ $q(['size' => $n]) }}">{{ $n }}</a>@endforeach</div>
</div>

<div style="padding:6px 20px 24px">
    @forelse($rows as $r)
        @php $x = $r['row']; @endphp
        <a class="row" href="{{ $r['url'] }}" style="min-height:60px">
            @if($on === 'heat')
                <span><b style="font-size:16px">{{ $x->furnace?->code ?? $x->furnace?->name }} · <code>{{ $x->heat_number }}</code></b><br><span class="muted" style="font-size:13px">{{ $x->ironGrade?->code }} · {{ ($x->actual_start_at ?? $x->planned_start_at ?? $x->created_at)?->format('j M H:i') }}</span></span>
                <span class="muted" style="font-size:12.5px">{{ str_replace('_', ' ', strtolower($x->status)) }}</span>
            @else
                <span><b style="font-size:16px"><code>{{ $x->label }}</code></b><br><span class="muted" style="font-size:13px">{{ $x->product?->name }} · {{ $x->quantity_poured }} pcs · {{ $x->created_at?->format('j M H:i') }}</span></span>
                <span class="muted" style="font-size:12.5px">{{ str_replace('_', ' ', strtolower($x->status)) }}</span>
            @endif
        </a>
    @empty
        <div class="muted" style="padding:16px 0;font-size:14px">Nothing in this range. Widen it above.</div>
    @endforelse
</div>
@endsection
