@extends('layouts.app')

@section('title', 'Before pour '.$number)
@section('breadcrumb', 'Furnace / '.$heat->heat_number.' / Pour gate')

{{-- design: F4·4 pour gate. Advisory notes a failed check and lets the pour go on; enforced blocks it (set in Rules per furnace type). --}}
@section('content')
<div class="fl-gloves fl-has-tabs">
    @include('floor.furnace._frame', ['active' => 'heat'])

    <h1 style="margin:0;font-size:22px">Before pour {{ $number }} · {{ $heat->heat_number }} {{ $heat->ironGrade?->code }} · {{ $heat->furnace?->code }}</h1>
    <div class="fl-blockers" style="margin-top:10px">
        @forelse ($gate['checks'] ?? [] as $c)
            @php($state = $c['ok'] === true ? 'pass' : ($c['ok'] === false && $mode === 'ENFORCED' ? 'fail' : 'warn'))
            <x-floor.blocker :state="$state" :title="$c['label']" :detail="$c['detail'].($state === 'warn' ? ($c['ok'] === false ? ' Pour anyway, noted.' : '') : '')" />
        @empty
            <x-floor.blocker state="clear" title="Nothing blocks this pour" :detail="$mode === 'OFF' ? 'The pour gate is off in Rules.' : 'No check applies to this grade.'" />
        @endforelse
    </div>
    @if ($mode === 'ADVISORY')<p style="color:var(--fl-meta);font-size:13.5px">A missing or failed check is noted on the pour and does not stop it.</p>@endif

    <form method="GET" action="{{ route('floor.furnace.heat.pour.create', $heat) }}">
        <x-floor.count-field name="ladle" label="Metal in ladle {{ $number }}" hint="kg" placeholder="Tap to type kg" :value="old('ladle')" />
        @error('ladle_kg')<div class="alert alert-error">{{ $message }}</div>@enderror
        <x-floor.keypad save="Open the gate · pour" :disabled="$blocked" />
        @if ($blocked)<p style="color:var(--fl-meta);font-size:13.5px">Enforced in Rules: fix the check above first.</p>@endif
    </form>
</div>
@endsection
