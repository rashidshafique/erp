@extends('layouts.app')

@section('title', 'Close '.$heat->heat_number)
@section('breadcrumb', 'Furnace / '.$heat->heat_number.' / Close')

{{-- design: F4·6 close heat, metal balance. Closing is never blocked by unaccounted metal. --}}
@section('content')
@php($kg = fn ($v) => \App\Support\Formats::number((float) $v, 0))
<div class="fl-gloves fl-has-tabs" x-data="{ tapped: {{ $tapped ?? 'null' }}, left: {{ (float) ($heat->leftover_metal_kg ?? 0) }}, poured: {{ $b['poured_kg'] }},
    get gap() { return this.tapped === null || this.tapped === '' ? null : Math.round((this.tapped - this.poured - (this.left || 0)) * 10) / 10; } }">
    @include('floor.furnace._frame', ['active' => 'heat'])

    @if ($closed)
        <h1 style="margin:0;font-size:22px">{{ $heat->heat_number }} is closed</h1>
        <x-floor.big-button label="Back to the next heat" :href="route('floor.furnace.next-heat')" />
    @else
        <h1 style="margin:0;font-size:22px">Close {{ $heat->heat_number }}{{ $heat->isCupola() ? ' · bottom drop' : '' }}</h1>
        <p style="color:var(--fl-meta);font-size:13.5px;margin:4px 0 10px">Furnace off: now ({{ now()->format('H:i') }})</p>

        <div class="fl-block-head">Metal balance</div>
        <div class="fl-rows">
            <x-floor.row title="Charged" :age="$kg($b['charged_kg']).' kg'" />
            <x-floor.row :title="'Poured into moulds ('.$b['pours'].' '.\Illuminate\Support\Str::plural('pour', $b['pours']).')'" :age="$kg($b['poured_kg']).' kg'" />
        </div>

        <form method="POST" action="{{ route('floor.furnace.heat.close.store', $heat) }}">
            @csrf
            <div class="fl-rows">
                <div class="fl-row" style="display:flex;justify-content:space-between;align-items:center;gap:12px"><label for="tapped" style="flex:1">Tapped</label>
                    <input id="tapped" name="tapped_kg" type="number" step="any" min="0" x-model.number="tapped" style="min-height:56px;width:130px;font-size:20px;text-align:right"> kg</div>
                <div class="fl-row" style="display:flex;justify-content:space-between;align-items:center;gap:12px"><label for="left" style="flex:1">Metal left</label>
                    <input id="left" name="leftover_metal_kg" type="number" step="any" min="0" x-model.number="left" style="min-height:56px;width:130px;font-size:20px;text-align:right"> kg</div>
                <div class="fl-row"><label for="use" style="display:block">Where did it go</label>
                    <select id="use" name="leftover_use" style="min-height:56px;width:100%;font-size:18px"><option value="">—</option>
                        @foreach ($uses as $k => $label)<option value="{{ $k }}" @selected(old('leftover_use', $heat->leftover_use) === $k)>{{ $label }}</option>@endforeach</select></div>
            </div>

            <template x-if="gap !== null">
                <p :class="Math.abs(gap) >= 5 ? 'alert alert-warning' : ''" style="margin:10px 0">
                    <strong x-text="(Math.abs(gap) >= 5 ? '! ' : '') + 'Unaccounted: ' + gap + ' kg'"></strong>
                    <span style="color:var(--fl-meta)"> · tapped − poured − left. The planner is told above the limit set in Rules. Closing is never blocked.</span>
                </p>
            </template>

            @if ($check)
                <x-floor.blocker state="warn" title="The run is shorter than expected" :detail="$check['actual'].' min of '.$check['expected'].' min expected ('.$check['percent'].'%). Say how the campaign ended.'" />
                <label style="display:flex;gap:10px;min-height:56px;align-items:center"><input type="radio" name="drop_outcome" value="FINISHED" @checked(old('drop_outcome') === 'FINISHED')> Campaign finished</label>
                <label style="display:flex;gap:10px;min-height:56px;align-items:center"><input type="radio" name="drop_outcome" value="PREMATURE" @checked(old('drop_outcome') === 'PREMATURE')> Dropped early</label>
                <input name="drop_reason" placeholder="Why, if dropped early" maxlength="1000" value="{{ old('drop_reason') }}" style="min-height:56px;width:100%;font-size:18px">
            @endif

            <label for="note" class="fl-block-head" style="display:block">Note (optional)</label>
            <input id="note" name="close_note" maxlength="500" value="{{ old('close_note') }}" style="min-height:56px;width:100%;font-size:18px" placeholder="e.g. spill at ladle 2">
            <div style="margin-top:12px"><x-floor.big-button label="Close heat" sub="chip clears · back to the next heat" /></div>
        </form>
    @endif
</div>
@endsection
