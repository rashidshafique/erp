@extends('layouts.app')

@section('title', 'Pour '.$number)
@section('breadcrumb', 'Furnace / '.$heat->heat_number.' / Pour '.$number)

{{-- design: F4·5 pour by pattern on a keypad. Poured can never go above ready. --}}
@section('content')
<div class="fl-gloves fl-has-tabs">
    @include('floor.furnace._frame', ['active' => 'heat'])

    <h1 style="margin:0;font-size:22px">Pour {{ $number }} · {{ $heat->heat_number }} · ladle {{ \App\Support\Formats::number($ladle, 0) }} kg</h1>
    @if ($advice)<x-floor.blocker state="warn" title="Poured without a clear check" :detail="$advice" />@endif

    <form method="POST" action="{{ route('floor.furnace.heat.pour.store', $heat) }}">
        @csrf
        <input type="hidden" name="ladle_kg" value="{{ $ladle }}">
        @forelse ($rows as $key => $r)
            <x-floor.count-field :name="'poured['.$key.']'" :label="$r['revision']->pattern->code.' '.$r['revision']->pattern->name"
                :max="$r['ready']" :hint="$r['order']->production_order_number.' · ready '.$r['ready']"
                :above="'More than are ready ('.$r['ready'].'). Lower it to save.'" placeholder="Tap to count" />
        @empty
            <div class="fl-row-empty">No moulds are ready to pour. Ask the moulders to count what is ready.</div>
        @endforelse
        <p style="color:var(--fl-meta);font-size:13.5px">{{ $poured }} moulds poured on this heat so far · {{ $readyTotal }} ready now.</p>
        <details style="margin-bottom:10px"><summary style="min-height:48px;display:flex;align-items:center">Pour temperature (optional)</summary>
            <input type="number" step="any" name="pour_temperature_c" placeholder="°C" style="min-height:56px;font-size:18px;width:100%">
            <input name="out_of_window_reason" placeholder="Reason, if outside the grade's window" maxlength="500" style="min-height:56px;font-size:18px;width:100%;margin-top:8px">
        </details>
        <x-floor.keypad save="Save pour {{ $number }} · {total} moulds" :disabled="$rows->isEmpty()" />
    </form>
</div>
@endsection
