@php
    $timeline = app(\App\Services\Production\CupolaTimeline::class);
    $burnIn = $timeline->burnInMinutes($heat);
    $fields = ['actual_start_at' => 'Light-up', 'blast_on_at' => 'Blast on', 'first_tap_at' => 'First tap', 'dropped_at' => 'Bottom drop'];
    $at = fn ($v) => $v?->format('Y-m-d\TH:i');
    $minutesBetween = fn ($from, $to) => $from && $to ? (int) $from->diffInMinutes($to) : null;
    $running = $heat->first_tap_at && ! $heat->dropped_at;
@endphp
<div class="panel">
    <div class="panel-header"><h2>Cupola timeline</h2>@if ($running)<span class="status status-on-hold">Running {{ intdiv((int) $heat->first_tap_at->diffInMinutes(now()), 60) }} h {{ (int) $heat->first_tap_at->diffInMinutes(now()) % 60 }} min since first tap</span>@elseif ($heat->dropped_at)<span class="status status-completed">Dropped</span>@endif</div>
    <div class="panel-body">
        <form method="POST" action="{{ route('heats.timeline', $heat) }}" class="form-grid" style="align-items:end">
            @csrf @method('PUT')
            @foreach ($fields as $name => $label)
                <div class="field">
                    <label for="tl-{{ $name }}">{{ $label }}</label>
                    <div style="display:flex;gap:6px">
                        <input id="tl-{{ $name }}" type="datetime-local" name="{{ $name }}" value="{{ old($name, $at($heat->{$name})) }}">
                        <button type="button" class="btn btn-small" onclick="var i=document.getElementById('tl-{{ $name }}'),d=new Date();d.setMinutes(d.getMinutes()-d.getTimezoneOffset());i.value=d.toISOString().slice(0,16)">Now</button>
                    </div>
                </div>
            @endforeach
            <div class="field">
                <label for="tl-tapped">Metal tapped (kg)</label>
                <input id="tl-tapped" type="number" name="actual_tapped_weight_kg" min="0" step="0.1" value="{{ old('actual_tapped_weight_kg', $heat->actual_tapped_weight_kg) }}">
            </div>
            <div><button class="btn btn-primary" type="submit">Save timeline</button></div>
        </form>
        <p class="muted small" style="margin:12px 0 0">
            @if ($burnIn !== null)Blast on to first tap: {{ $burnIn }} min. @endif
            @if ($m = $minutesBetween($heat->actual_start_at, $heat->dropped_at))Light-up to bottom drop: {{ intdiv($m, 60) }} h {{ $m % 60 }} min. @endif
            The first pour started on this heat sets the first tap if it is blank; pours in the first {{ \App\Support\PouringRules::minutes('first_iron_minutes') }} minutes after it are marked as first iron (Settings → Pouring rules).
        </p>
    </div>
</div>

