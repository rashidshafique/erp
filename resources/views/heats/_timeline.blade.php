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
        @if ($check = session('drop_check'))
            @php $hm = fn (int $m) => intdiv($m, 60).' h '.str_pad((string) ($m % 60), 2, '0', STR_PAD_LEFT).' min'; @endphp
            <div class="alert alert-warning" id="drop-check">
                <strong>Was the campaign finished?</strong>
                The bottom drop is {{ $hm($check['actual']) }} into the run, {{ $check['percent'] }}% of the {{ $hm($check['expected']) }} expected for the target metal (under the {{ $check['threshold'] }}% that counts as finished).
                <form method="POST" action="{{ route('heats.timeline', $heat) }}" style="margin-top:8px;display:grid;gap:6px">
                    @csrf @method('PUT')
                    @foreach (array_keys($fields) as $name)<input type="hidden" name="{{ $name }}" value="{{ old($name) }}">@endforeach
                    <input type="hidden" name="actual_tapped_weight_kg" value="{{ old('actual_tapped_weight_kg') }}">
                    <label style="margin:0"><input type="radio" name="drop_outcome" value="FINISHED" @checked(old('drop_outcome') === 'FINISHED')> Finished: the work is done</label>
                    <label style="margin:0"><input type="radio" name="drop_outcome" value="PREMATURE" @checked(old('drop_outcome', 'PREMATURE') === 'PREMATURE')> Dropped early because of a problem</label>
                    <label for="drop_reason" style="margin:0">Why was it dropped early?</label>
                    <textarea id="drop_reason" name="drop_reason" rows="2" maxlength="1000" placeholder="e.g. bridging in the stack, blast failure, no metal">{{ old('drop_reason') }}</textarea>
                    @error('drop_reason')<div class="error">{{ $message }}</div>@enderror
                    <div><button class="btn btn-primary" type="submit">Save bottom drop</button> <a class="btn" href="{{ route('heats.show', $heat) }}">Cancel</a></div>
                </form>
            </div>
        @endif
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
                <input id="tl-tapped" type="number" name="actual_tapped_weight_kg" min="0" step="any" value="{{ old('actual_tapped_weight_kg', $heat->actual_tapped_weight_kg) }}">
            </div>
            <div><button class="btn btn-primary" type="submit">Save timeline</button></div>
        </form>
        @if ($heat->outcomeLabel())
            <p style="margin:10px 0 0"><span class="status {{ $heat->drop_outcome === 'FINISHED' ? 'status-completed' : 'status-cancelled' }}">{{ $heat->outcomeLabel() }}</span>
                <span class="muted small">at {{ $heat->drop_actual_minutes !== null ? intdiv($heat->drop_actual_minutes, 60).' h '.str_pad((string) ($heat->drop_actual_minutes % 60), 2, '0', STR_PAD_LEFT).' min of '.intdiv((int) $heat->drop_expected_minutes, 60).' h '.str_pad((string) ((int) $heat->drop_expected_minutes % 60), 2, '0', STR_PAD_LEFT).' min expected' : 'the bottom drop' }}@if ($heat->drop_reason): {{ $heat->drop_reason }}@endif</span></p>
        @endif
        <p class="muted small" style="margin:12px 0 0">
            @if ($burnIn !== null)Blast on to first tap: {{ $burnIn }} min. @endif
            @if ($m = $minutesBetween($heat->actual_start_at, $heat->dropped_at))Light-up to bottom drop: {{ intdiv($m, 60) }} h {{ $m % 60 }} min. @endif
            The first pour started on this heat sets the first tap if it is blank; pours in the first {{ \App\Support\PouringRules::minutes('first_iron_minutes') }} minutes after it are marked as first iron (Settings → Pouring rules).
        </p>
    </div>
</div>

