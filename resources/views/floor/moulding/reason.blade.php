@extends('layouts.app')

@section('title', 'Moulds short')
@section('breadcrumb', 'Moulding / Count / Short')

{{-- design: F3·4 shortfall with a reason per short pattern; the moulds are written off and needed again. --}}
@section('content')
<div class="fl-tablet fl-has-tabs">
    @include('floor.moulding._frame')

    <h1 style="margin:0 0 4px;font-size:22px">{{ $short->sum(fn ($s) => $s['expected'] - $s['counted']) }} moulds short</h1>
    <p style="color:var(--fl-meta);font-size:13.5px;margin:0 0 12px">Pick why for each pattern. The missing moulds are written off and go back on what the orders still need, for the planner's Remake needed.</p>

    <form method="POST" action="{{ route('floor.moulding.count.store') }}">@csrf
        <input type="hidden" name="heat_id" value="{{ $heatId }}">
        @foreach ($counted as $id => $n)<input type="hidden" name="counted[{{ $id }}]" value="{{ $n }}">@endforeach

        @foreach ($short as $id => $s)
            <div class="fl-block-head">! {{ $s['expected'] - $s['counted'] }} short · {{ $s['revision']->pattern->code }} {{ $s['revision']->pattern->name }} <span style="text-transform:none;letter-spacing:0;font-weight:400">· records {{ $s['expected'] }}, counted {{ $s['counted'] }}</span></div>
            <div class="fl-rows" role="radiogroup" aria-label="Why short for {{ $s['revision']->pattern->code }}">
                @foreach (\App\Models\MouldCount::REASONS as $key => $label)
                    <label class="fl-row" style="grid-template-columns:32px minmax(0,1fr);cursor:pointer">
                        <input type="radio" name="reason[{{ $id }}]" value="{{ $key }}" @checked(old('reason.'.$id) === $key) style="width:24px;height:24px" required>
                        <span class="fl-row-title">{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        @endforeach

        <label class="fl-count" style="margin-top:16px"><span class="fl-count-label">Note (optional)</span>
            <input name="note" type="text" maxlength="300" value="{{ old('note') }}" placeholder="e.g. two drags dropped by the crane" style="height:56px;font-size:16px;text-align:left;font-weight:400">
        </label>
        <div style="display:flex;gap:12px;margin-top:8px">
            <a href="{{ route('floor.moulding.count') }}" class="fl-btn" style="min-height:56px">Recount</a>
            <button class="fl-btn is-primary" style="min-height:56px;flex:1">Write off {{ $short->sum(fn ($s) => $s['expected'] - $s['counted']) }} and continue</button>
        </div>
    </form>
</div>
@endsection
