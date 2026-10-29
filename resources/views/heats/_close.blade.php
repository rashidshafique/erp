{{-- Closing the heat when the furnace is turned off (a cupola closes at its bottom drop, so for one this records the leftover metal only). --}}
@php
    $cupola = $heat->isCupola();
    $closed = $heat->status === 'CLOSED';
    $notPoured = app(\App\Services\Production\HeatBalance::class)->for($heat)['unpoured_kg'];
    $suggest = $notPoured !== null && $notPoured > 0 ? $notPoured : null;
@endphp
@if ($heat->status !== 'CANCELLED' && (! $closed || ($cupola && $heat->leftover_use === null)))
<div class="panel" id="close-heat">
    <div class="panel-header"><h2>{{ $cupola ? 'Metal left after the drop' : 'Close heat' }}</h2><span class="muted small">{{ $cupola ? 'What was left and where it went.' : 'When the furnace is turned off at the end of the day.' }}</span></div>
    <div class="panel-body">
        @error('close')<div class="alert alert-error">{{ $message }}</div>@enderror
        @if ($cupola && ! $heat->dropped_at)
            <p class="muted">The heat closes at its bottom drop. Enter it on the timeline.</p>
        @else
        <form method="POST" action="{{ route('heats.close', $heat) }}" class="form-grid" style="align-items:end">@csrf
            @unless ($cupola)<label>Furnace off<input type="datetime-local" name="closed_at" value="{{ old('closed_at', now()->format('Y-m-d\TH:i')) }}"></label>@endunless
            <label>Metal left (kg)<input type="number" name="leftover_metal_kg" min="0" step="1" value="{{ old('leftover_metal_kg', $suggest !== null ? round($suggest) : null) }}" placeholder="0">@if ($suggest !== null)<span class="muted small">Tapped, not poured: {{ number_format((float) $suggest, 1) }} kg. Change it if the weighed metal differs.</span>@endif</label>
            <label>Where it went<select name="leftover_use"><option value="">—</option>@foreach (\App\Models\Heat::LEFTOVER_USES as $k => $l)<option value="{{ $k }}" @selected(old('leftover_use') === $k)>{{ $l }}</option>@endforeach</select></label>
            @error('leftover_use')<div class="error">{{ $message }}</div>@enderror
            <label style="grid-column:1/-1">Note<input name="close_note" maxlength="500" value="{{ old('close_note') }}"></label>
            <div><button class="btn btn-primary" type="submit">{{ $cupola ? 'Save leftover metal' : 'Close heat' }}</button></div>
        </form>
        @endif
    </div>
</div>
@elseif ($closed && ($heat->leftover_metal_kg !== null || $heat->close_note))
<div class="panel"><div class="panel-header"><h2>Closed</h2><span class="muted small">{{ $heat->closed_at?->displayShortDateTime() }}</span></div>
    <div class="panel-body">@if ($heat->leftover_metal_kg !== null){{ \App\Support\Formats::number($heat->leftover_metal_kg, 0) }} kg left · {{ \App\Models\Heat::LEFTOVER_USES[$heat->leftover_use] ?? '' }}@endif
        @if ($heat->close_note)<div class="muted">{{ $heat->close_note }}</div>@endif</div></div>
@endif
