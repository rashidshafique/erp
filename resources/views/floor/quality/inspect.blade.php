@extends('layouts.app')

@section('title', 'Inspect '.$lot->label)
@section('breadcrumb', 'Quality / '.$lot->label.' / Inspect')

{{-- design: Q·4 inspection counted on the keypad, with defect lines. --}}
@section('content')
<div class="fl-tablet fl-has-tabs">
    @include('floor.quality._frame', ['active' => 'lots'])

    <h1 style="margin:0;font-size:22px">Final inspection · {{ $lot->label }}</h1>
    <p style="color:var(--fl-meta);font-size:13.5px;margin:4px 0 12px">{{ $lot->product?->name }} · {{ $toCheck }} to check · {{ auth()->user()?->name }}</p>

    <form method="POST" action="{{ route('floor.quality.lot.review', $lot) }}">@csrf
        <div class="fl-count-pad">
            <div>
                <x-floor.count-field name="accepted" id="q-accepted" label="Accepted" :value="old('accepted')" :max="$toCheck" hint="Pieces that passed" placeholder="0" />
                <x-floor.count-field name="rework" id="q-rework" label="Rework" :value="old('rework')" :max="$toCheck" hint="Pieces to fix and check again" placeholder="0" />
                <x-floor.count-field name="rejected" id="q-rejected" label="Rejected" :value="old('rejected')" :max="$toCheck" hint="Pieces that are scrap" placeholder="0" />
                <div class="fl-block-head">Defects found (optional)</div>
                @for ($i = 0; $i < 3; $i++)
                    <div class="fl-row" style="display:flex;gap:8px;align-items:center">
                        <select name="defects[{{ $i }}][id]" style="min-height:56px;flex:1;font-size:16px"><option value="">—</option>@foreach ($defects as $d)<option value="{{ $d->id }}" @selected(old("defects.$i.id") === $d->id)>{{ $d->name }}</option>@endforeach</select>
                        <input type="number" name="defects[{{ $i }}][qty]" min="0" max="{{ $toCheck }}" inputmode="numeric" value="{{ old('defects.'.$i.'.qty') }}" placeholder="Pcs" aria-label="Defect pieces" style="min-height:56px;width:90px;font-size:18px;text-align:right">
                        <select name="defects[{{ $i }}][kind]" style="min-height:56px;font-size:16px"><option value="REJECT">reject</option><option value="REWORK" @selected(old("defects.$i.kind") === 'REWORK')>rework</option></select>
                    </div>
                @endfor
                <p style="color:var(--fl-meta);font-size:13.5px">Defect pieces cannot add up to more than the rework and rejected pieces.</p>
            </div>
            <x-floor.keypad save="Review · {total} checked" />
        </div>
    </form>
</div>
@endsection
